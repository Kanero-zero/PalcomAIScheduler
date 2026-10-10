<?php

namespace App\Services\Scheduling;

use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ScheduleApprovalService
{
    public function __construct(
        protected SchedulingEngine $engine
    ) {}

    /**
     * Setujui instruktur pengganti untuk jadwal kelas yang terdampak izin.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         substitution_id: int,
     *         leave_id: int,
     *         schedule_id: int,
     *         status: string,
     *         assigned_instructor: array{id: int, name: string},
     *         decision_by: int|null,
     *         decision_by_name: string|null,
     *         decision_at: string|null
     *     }
     * }
     */
    public function approveSubstitution(
        int $leaveId,
        int $scheduleId,
        int $replacementInstructorId,
        ?string $notes = null,
    ): array {
        Gate::authorize('manage-schedule-approval');

        $leave = InstructorLeave::with('instructor')->find($leaveId);
        if (! $leave) {
            throw ValidationException::withMessages([
                'leave_id' => 'Pengajuan izin tidak ditemukan.',
            ]);
        }

        $schedule = Schedule::with(['courseClass', 'room', 'instructor'])->find($scheduleId);
        if (! $schedule) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal kelas tidak ditemukan.',
            ]);
        }

        $replacementInstructor = Instructor::find($replacementInstructorId);
        if (! $replacementInstructor) {
            throw ValidationException::withMessages([
                'replacement_instructor_id' => 'Instruktur pengganti tidak ditemukan.',
            ]);
        }

        // 1. Verifikasi relasi jadwal dengan izin
        $this->validateScheduleBelongsToLeave($schedule, $leave);

        // 2. Cegah keputusan ganda (idempotency check)
        $existingSub = ScheduleSubstitution::where('instructor_leave_id', $leaveId)
            ->where('schedule_id', $scheduleId)
            ->first();

        if ($existingSub && $existingSub->status !== 'pending') {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal ini sudah memiliki keputusan persetujuan dan tidak dapat diubah kembali.',
            ]);
        }

        // 3. Validasi catatan opsional (max 500 karakter)
        if ($notes !== null && mb_strlen($notes) > 500) {
            throw ValidationException::withMessages([
                'notes' => 'Catatan admin tidak boleh melebihi 500 karakter.',
            ]);
        }

        // 4. Validasi ruangan (MVP: blokir jika perlu perpindahan ruangan atau ruangan bermasalah)
        if ($schedule->room) {
            $roomEval = $this->engine->evaluateRoom($schedule->room, $schedule);
            if (! $roomEval->isValid || $roomEval->requiresRoomChange || ! $roomEval->hasUsableRoom()) {
                throw ValidationException::withMessages([
                    'room' => 'Persetujuan ditunda: Ruangan kelas bermasalah atau memerlukan pemindahan ruangan. Selesaikan kendala ruangan sebelum menetapkan instruktur pengganti.',
                ]);
            }
        }

        // 5. Re-evaluasi kelayakan kandidat pengganti via SchedulingEngine
        $candidateEval = $this->engine->evaluateCandidate($replacementInstructor, $schedule);
        if (! $candidateEval->isValid) {
            if ($candidateEval->hasScheduleConflict) {
                throw ValidationException::withMessages([
                    'replacement_instructor_id' => 'Instruktur pengganti memiliki bentrok jadwal mengajar lain pada waktu tersebut.',
                ]);
            }

            if (! $candidateEval->competencyMatched) {
                throw ValidationException::withMessages([
                    'replacement_instructor_id' => 'Instruktur yang dipilih tidak memenuhi kualifikasi kompetensi kelas ini.',
                ]);
            }

            throw ValidationException::withMessages([
                'replacement_instructor_id' => 'Instruktur yang dipilih tidak memenuhi kualifikasi atau tidak tersedia: '.($candidateEval->disqualificationReason ?? 'Tidak valid'),
            ]);
        }

        // 6. Cegah bentrok lintas kelas (cross-schedule double booking)
        $hasCrossScheduleConflict = Schedule::query()
            ->where('id', '!=', $scheduleId)
            ->where('instructor_id', $replacementInstructorId)
            ->whereDate('date', $schedule->date)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $schedule->end_time)
            ->where('end_time', '>', $schedule->start_time)
            ->exists();

        if ($hasCrossScheduleConflict) {
            throw ValidationException::withMessages([
                'replacement_instructor_id' => 'Instruktur pengganti sudah ditugaskan pada kelas lain yang memiliki bentrok waktu.',
            ]);
        }

        // 7. Simpan keputusan dan update schedules.instructor_id dalam 1 DB transaction
        return DB::transaction(function () use ($leave, $schedule, $replacementInstructor, $existingSub, $notes) {
            $originalInstructorId = $existingSub?->original_instructor_id ?? $schedule->instructor_id;

            $user = auth()->user();
            $decisionAt = now();

            $substitution = ScheduleSubstitution::updateOrCreate(
                [
                    'instructor_leave_id' => $leave->id,
                    'schedule_id' => $schedule->id,
                ],
                [
                    'original_instructor_id' => $originalInstructorId,
                    'replacement_instructor_id' => $replacementInstructor->id,
                    'status' => 'approved',
                    'decision_by' => $user?->id,
                    'decision_at' => $decisionAt,
                    'rejection_reason' => null,
                    'notes' => $notes !== null ? trim($notes) : null,
                ]
            );

            // Perbarui instruktur pada jadwal kelas
            $schedule->update([
                'instructor_id' => $replacementInstructor->id,
            ]);

            return [
                'success' => true,
                'message' => 'Instruktur pengganti berhasil disetujui dan jadwal kelas telah diperbarui.',
                'data' => [
                    'substitution_id' => $substitution->id,
                    'leave_id' => $leave->id,
                    'schedule_id' => $schedule->id,
                    'status' => 'approved',
                    'assigned_instructor' => [
                        'id' => $replacementInstructor->id,
                        'name' => $replacementInstructor->name,
                    ],
                    'decision_by' => $user?->id,
                    'decision_by_name' => $user?->name,
                    'decision_at' => $decisionAt->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * Tolak rekomendasi pengganti untuk jadwal kelas yang terdampak izin.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         substitution_id: int,
     *         leave_id: int,
     *         schedule_id: int,
     *         status: string,
     *         rejection_reason: string,
     *         decision_by: int|null,
     *         decision_by_name: string|null,
     *         decision_at: string|null
     *     }
     * }
     */
    public function rejectSubstitution(
        int $leaveId,
        int $scheduleId,
        string $rejectionReason,
    ): array {
        Gate::authorize('manage-schedule-approval');

        $trimmedReason = trim($rejectionReason);
        $reasonLength = mb_strlen($trimmedReason);
        if ($reasonLength < 10 || $reasonLength > 500) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan wajib diisi minimal 10 karakter dan maksimal 500 karakter.',
            ]);
        }

        $leave = InstructorLeave::with('instructor')->find($leaveId);
        if (! $leave) {
            throw ValidationException::withMessages([
                'leave_id' => 'Pengajuan izin tidak ditemukan.',
            ]);
        }

        $schedule = Schedule::with(['courseClass', 'room', 'instructor'])->find($scheduleId);
        if (! $schedule) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal kelas tidak ditemukan.',
            ]);
        }

        // 1. Verifikasi relasi jadwal dengan izin
        $this->validateScheduleBelongsToLeave($schedule, $leave);

        // 2. Cegah keputusan ganda (idempotency check)
        $existingSub = ScheduleSubstitution::where('instructor_leave_id', $leaveId)
            ->where('schedule_id', $scheduleId)
            ->first();

        if ($existingSub && $existingSub->status !== 'pending') {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal ini sudah memiliki keputusan persetujuan dan tidak dapat diubah kembali.',
            ]);
        }

        // 3. Simpan penolakan dalam DB transaction (Jadwal kelas TIDAK diubah sama sekali)
        return DB::transaction(function () use ($leave, $schedule, $existingSub, $trimmedReason) {
            $originalInstructorId = $existingSub?->original_instructor_id ?? $schedule->instructor_id;

            $user = auth()->user();
            $decisionAt = now();

            $substitution = ScheduleSubstitution::updateOrCreate(
                [
                    'instructor_leave_id' => $leave->id,
                    'schedule_id' => $schedule->id,
                ],
                [
                    'original_instructor_id' => $originalInstructorId,
                    'replacement_instructor_id' => null,
                    'status' => 'rejected',
                    'decision_by' => $user?->id,
                    'decision_at' => $decisionAt,
                    'rejection_reason' => $trimmedReason,
                    'notes' => null,
                ]
            );

            return [
                'success' => true,
                'message' => 'Rekomendasi pengganti berhasil ditolak. Jadwal kelas tidak mengalami perubahan.',
                'data' => [
                    'substitution_id' => $substitution->id,
                    'leave_id' => $leave->id,
                    'schedule_id' => $schedule->id,
                    'status' => 'rejected',
                    'rejection_reason' => $trimmedReason,
                    'decision_by' => $user?->id,
                    'decision_by_name' => $user?->name,
                    'decision_at' => $decisionAt->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * Memverifikasi apakah jadwal kelas benar-benar terasosiasi dan terdampak oleh izin instruktur.
     */
    protected function validateScheduleBelongsToLeave(Schedule $schedule, InstructorLeave $leave): void
    {
        $scheduleDate = Carbon::parse($schedule->date)->format('Y-m-d');
        $leaveDate = Carbon::parse($leave->date)->format('Y-m-d');

        if ($scheduleDate !== $leaveDate) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal kelas bukan merupakan bagian dari pengajuan izin yang dipilih.',
            ]);
        }

        // Periksa tumpang tindih waktu (start_time < leave_end && end_time > leave_start)
        $scheduleStart = substr((string) $schedule->start_time, 0, 5);
        $scheduleEnd = substr((string) $schedule->end_time, 0, 5);
        $leaveStart = substr((string) $leave->start_time, 0, 5);
        $leaveEnd = substr((string) $leave->end_time, 0, 5);

        if ($scheduleStart >= $leaveEnd || $scheduleEnd <= $leaveStart) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal kelas bukan merupakan bagian dari pengajuan izin yang dipilih.',
            ]);
        }

        // Instruktur jadwal harus sesuai instruktur izin, atau memiliki riwayat original_instructor_id yang sesuai
        $existingSub = ScheduleSubstitution::where('instructor_leave_id', $leave->id)
            ->where('schedule_id', $schedule->id)
            ->first();

        $originalInstructorId = $existingSub?->original_instructor_id ?? $schedule->instructor_id;

        if ($originalInstructorId !== $leave->instructor_id) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal kelas bukan merupakan bagian dari pengajuan izin yang dipilih.',
            ]);
        }
    }
}
