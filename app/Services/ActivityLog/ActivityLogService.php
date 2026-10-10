<?php

namespace App\Services\ActivityLog;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use App\Services\Scheduling\DTOs\SchedulingResult;
use Illuminate\Database\Eloquent\Collection;

class ActivityLogService
{
    /**
     * Catat aktivitas ketika pengajuan izin baru dibuat.
     */
    public function logLeaveCreated(InstructorLeave $leave, ?User $actor = null): ActivityLog
    {
        $instructorName = $leave->instructor?->name ?? 'Instruktur';
        $actorName = $actor?->name ?? auth()->user()?->name ?? 'Admin';
        $dateFormatted = $leave->date ? $leave->date->format('d M Y') : '-';

        return ActivityLog::create([
            'user_id' => $actor?->id ?? auth()->id(),
            'type' => 'leave',
            'state' => 'pending',
            'actor' => $actorName,
            'title' => 'Pengajuan izin instruktur dicatat',
            'description' => "Pengajuan izin dicatat untuk instruktur {$instructorName} pada tanggal {$dateFormatted} ({$leave->start_time} - {$leave->end_time}).",
            'subject' => "Pengajuan Izin #{$leave->id}",
            'instructor_leave_id' => $leave->id,
            'metadata' => [
                'leave_id' => $leave->id,
                'instructor_id' => $leave->instructor_id,
                'instructor_name' => $instructorName,
                'date' => $leave->date?->format('Y-m-d'),
                'start_time' => $leave->start_time,
                'end_time' => $leave->end_time,
                'reason' => $leave->reason,
            ],
        ]);
    }

    /**
     * Catat aktivitas hasil evaluasi Scheduling Engine.
     * Menggunakan fingerprint untuk mencegah duplikasi jika evaluasi dijalankan ulang tanpa perubahan status.
     */
    public function logEngineEvaluation(SchedulingResult $result, ?User $actor = null): ?ActivityLog
    {
        if (! $result->leaveId) {
            return null;
        }

        // Hitung fingerprint berdasarkan status dan snapshot kelas terdampak
        $scheduleSnapshots = array_map(function ($s) {
            $candidateId = $s->bestCandidate?->instructorId ?? 'none';
            $roomStatus = $s->roomEvaluation?->status ?? 'none';

            return "{$s->scheduleId}:{$s->status}:{$candidateId}:{$roomStatus}";
        }, $result->affectedSchedules);
        sort($scheduleSnapshots);

        $fingerprint = sha1("engine:{$result->leaveId}:affected:{$result->totalAffectedSchedules}:resolved:{$result->totalResolvedSchedules}:snapshots:".implode('|', $scheduleSnapshots));

        // Cek log engine terakhir untuk leave_id ini. Jika fingerprint identik, jangan catat duplikat.
        $latestLog = ActivityLog::where('type', 'engine')
            ->where('instructor_leave_id', $result->leaveId)
            ->latest('id')
            ->first();

        if ($latestLog && $latestLog->fingerprint === $fingerprint) {
            return null;
        }

        $title = $result->allSchedulesResolved
            ? 'Kandidat pengganti ditemukan untuk semua kelas'
            : ($result->totalResolvedSchedules > 0
                ? 'Sebagian kandidat pengganti ditemukan'
                : 'Pemeriksaan kandidat dan ruangan selesai');

        $description = "Pemeriksaan kualifikasi kompetensi, ketersediaan, dan ruangan dijalankan. {$result->totalResolvedSchedules} dari {$result->totalAffectedSchedules} sesi kelas terdampak terselesaikan.";

        return ActivityLog::create([
            'user_id' => $actor?->id ?? auth()->id(),
            'type' => 'engine',
            'state' => 'success',
            'actor' => 'Sistem',
            'title' => $title,
            'description' => $description,
            'subject' => "Pengajuan Izin #{$result->leaveId}",
            'instructor_leave_id' => $result->leaveId,
            'fingerprint' => $fingerprint,
            'metadata' => [
                'leave_id' => $result->leaveId,
                'total_affected' => $result->totalAffectedSchedules,
                'total_resolved' => $result->totalResolvedSchedules,
                'all_resolved' => $result->allSchedulesResolved,
            ],
        ]);
    }

    /**
     * Catat aktivitas rekomendasi cerdas Google Gemini AI.
     * Mendukung status success, fallback, dan not_applicable secara konsisten.
     *
     * @param  array<string, mixed>  $aiSummary
     */
    public function logAiRecommendation(SchedulingResult $result, array $aiSummary, ?User $actor = null): ActivityLog
    {
        $status = $aiSummary['status'] ?? (($aiSummary['fallback_used'] ?? false) ? 'fallback' : 'success');

        $state = match ($status) {
            'fallback' => 'fallback',
            'not_applicable' => 'not_applicable',
            default => 'success',
        };

        $title = match ($status) {
            'fallback' => 'Analisis AI dialihkan ke Fallback',
            'not_applicable' => 'Analisis AI tidak diperlukan',
            default => 'Pemeringkatan kandidat selesai',
        };

        $description = match ($status) {
            'fallback' => 'Layanan Gemini AI tidak dapat dijangkau. Rekomendasi dialihkan ke hasil deterministik (Mode Fallback).',
            'not_applicable' => 'Tidak ada kandidat valid atau kelas terdampak yang perlu dianalisis oleh AI.',
            'partial' => 'Analisis AI selesai sebagian dengan rekomendasi gabungan AI dan deterministik.',
            default => 'Analisis AI memberikan pemeringkatan cerdas dan alasan rekomendasi terhadap kandidat yang lolos seleksi deterministik.',
        };

        $confidenceScores = [];
        foreach ($result->affectedSchedules as $scheduleEval) {
            if (! empty($scheduleEval->aiRecommendation['rankings'])) {
                foreach ($scheduleEval->aiRecommendation['rankings'] as $ranking) {
                    if (isset($ranking['confidence_score']) && is_numeric($ranking['confidence_score'])) {
                        $confidenceScores[] = (float) $ranking['confidence_score'];
                    }
                }
            }
        }
        $avgConfidence = ! empty($confidenceScores)
            ? round(array_sum($confidenceScores) / count($confidenceScores), 2)
            : ($aiSummary['confidence_score'] ?? null);

        // Sanitasi: Jangan menyimpan api_key atau authorization token pada metadata
        $cleanMetadata = [
            'leave_id' => $result->leaveId,
            'status' => $status,
            'confidence_score' => $avgConfidence,
            'fallback_used' => $aiSummary['fallback_used'] ?? false,
            'total_analyzed' => $aiSummary['total_analyzed'] ?? count($result->affectedSchedules),
        ];

        return ActivityLog::create([
            'user_id' => $actor?->id ?? auth()->id(),
            'type' => 'ai',
            'state' => $state,
            'actor' => 'Gemini AI',
            'title' => $title,
            'description' => $description,
            'subject' => "Pengajuan Izin #{$result->leaveId}",
            'instructor_leave_id' => $result->leaveId,
            'metadata' => $cleanMetadata,
        ]);
    }

    /**
     * Catat aktivitas persetujuan penugasan instruktur pengganti oleh admin.
     */
    public function logApproval(ScheduleSubstitution $substitution, Schedule $schedule, ?User $admin = null): ActivityLog
    {
        $adminUser = $admin ?? auth()->user();
        $replacementName = $substitution->replacementInstructor?->name
            ?? Instructor::find($substitution->replacement_instructor_id)?->name
            ?? 'Instruktur Pengganti';
        $className = $schedule->courseClass?->name ?? 'Kelas';

        return ActivityLog::create([
            'user_id' => $adminUser?->id,
            'type' => 'approval',
            'state' => 'success',
            'actor' => $adminUser?->name ?? 'Admin',
            'title' => 'Rekomendasi instruktur disetujui',
            'description' => "Admin memilih {$replacementName} sebagai instruktur pengganti untuk kelas {$className}.",
            'subject' => "Pengajuan Izin #{$substitution->instructor_leave_id}",
            'instructor_leave_id' => $substitution->instructor_leave_id,
            'schedule_id' => $schedule->id,
            'metadata' => [
                'substitution_id' => $substitution->id,
                'schedule_id' => $schedule->id,
                'replacement_instructor_id' => $substitution->replacement_instructor_id,
                'replacement_instructor_name' => $replacementName,
                'original_instructor_id' => $substitution->original_instructor_id,
                'notes' => $substitution->notes,
            ],
        ]);
    }

    /**
     * Catat aktivitas penolakan rekomendasi pengganti oleh admin.
     */
    public function logRejection(ScheduleSubstitution $substitution, Schedule $schedule, string $rejectionReason, ?User $admin = null): ActivityLog
    {
        $adminUser = $admin ?? auth()->user();
        $className = $schedule->courseClass?->name ?? 'Kelas';

        return ActivityLog::create([
            'user_id' => $adminUser?->id,
            'type' => 'approval',
            'state' => 'rejected',
            'actor' => $adminUser?->name ?? 'Admin',
            'title' => 'Usulan pengganti ditolak',
            'description' => "Admin menolak usulan pengganti untuk kelas {$className}. Alasan: {$rejectionReason}",
            'subject' => "Pengajuan Izin #{$substitution->instructor_leave_id}",
            'instructor_leave_id' => $substitution->instructor_leave_id,
            'schedule_id' => $schedule->id,
            'metadata' => [
                'substitution_id' => $substitution->id,
                'schedule_id' => $schedule->id,
                'rejection_reason' => $rejectionReason,
            ],
        ]);
    }

    /**
     * Catat aktivitas pembaruan jadwal kelas setelah persetujuan admin.
     */
    public function logScheduleUpdated(Schedule $schedule, Instructor $newInstructor, ?User $admin = null, ?int $leaveId = null): ActivityLog
    {
        $className = $schedule->courseClass?->name ?? 'Kelas';
        $roomName = $schedule->room?->name ?? 'Ruangan';
        $subject = "{$className} - {$roomName}";

        return ActivityLog::create([
            'user_id' => $admin?->id ?? auth()->id(),
            'type' => 'schedule',
            'state' => 'success',
            'actor' => 'Sistem',
            'title' => 'Jadwal kelas diperbarui',
            'description' => "Instruktur pengganti ({$newInstructor->name}) telah diterapkan pada kelas {$className} setelah persetujuan admin.",
            'subject' => $subject,
            'instructor_leave_id' => $leaveId,
            'schedule_id' => $schedule->id,
            'metadata' => [
                'schedule_id' => $schedule->id,
                'course_class_id' => $schedule->course_class_id,
                'class_name' => $className,
                'room_id' => $schedule->room_id,
                'room_name' => $roomName,
                'new_instructor_id' => $newInstructor->id,
                'new_instructor_name' => $newInstructor->name,
                'date' => $schedule->date?->format('Y-m-d') ?? (string) $schedule->date,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
            ],
        ]);
    }

    /**
     * Mengambil daftar aktivitas dalam format timeline array untuk frontend.
     *
     * @return list<array<string, mixed>>
     */
    public function getTimelineActivities(?string $type = null, ?string $state = null, int $limit = 50): array
    {
        /** @var Collection<int, ActivityLog> $logs */
        $logs = ActivityLog::query()
            ->forTimeline()
            ->byType($type)
            ->byState($state)
            ->limit($limit)
            ->get();

        return $logs->map(fn (ActivityLog $log) => $log->toTimelineArray())->values()->all();
    }
}
