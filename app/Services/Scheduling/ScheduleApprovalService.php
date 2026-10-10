<?php

namespace App\Services\Scheduling;

use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Services\ActivityLog\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
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

        // Validasi panjang catatan opsional sebelum transaksi
        if ($notes !== null && mb_strlen($notes) > 500) {
            throw ValidationException::withMessages([
                'notes' => 'Catatan admin tidak boleh melebihi 500 karakter.',
            ]);
        }

        try {
            return DB::transaction(function () use ($leaveId, $scheduleId, $replacementInstructorId, $notes) {
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

                // 1. Verifikasi relasi jadwal dengan izin dan pastikan tidak berstatus cancelled
                $this->validateScheduleBelongsToLeave($schedule, $leave);

                // 2. Cegah keputusan ganda (idempotency check di dalam transaksi)
                $existingSub = ScheduleSubstitution::where('instructor_leave_id', $leaveId)
                    ->where('schedule_id', $scheduleId)
                    ->first();

                if ($existingSub && $existingSub->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'schedule_id' => 'Jadwal ini sudah memiliki keputusan persetujuan dan tidak dapat diubah kembali.',
                    ]);
                }

                // 3. Validasi ketat ruangan: jadwal tanpa ruangan valid tidak boleh disetujui (MVP)
                if (! $schedule->room_id || ! $schedule->room) {
                    throw ValidationException::withMessages([
                        'room' => 'Jadwal kelas tidak memiliki ruangan yang valid sehingga persetujuan tidak dapat diproses.',
                    ]);
                }

                $roomEval = $this->engine->evaluateRoom($schedule->room, $schedule);
                if (! $roomEval->isValid || $roomEval->requiresRoomChange || ! $roomEval->hasUsableRoom()) {
                    throw ValidationException::withMessages([
                        'room' => 'Persetujuan ditunda: Ruangan kelas bermasalah atau memerlukan pemindahan ruangan. Selesaikan kendala ruangan sebelum menetapkan instruktur pengganti.',
                    ]);
                }

                // 4. Re-evaluasi kelayakan kandidat pengganti via SchedulingEngine di dalam transaksi
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

                // 5. Cegah bentrok lintas kelas (cross-schedule double booking) di dalam transaksi
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

                // 6. Simpan keputusan dan update schedules.instructor_id secara atomik
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

                // Catat aktivitas persetujuan dan perubahan jadwal secara atomik di dalam transaksi
                $activityLogService = app(ActivityLogService::class);
                $activityLogService->logApproval($substitution, $schedule, $user);
                $activityLogService->logScheduleUpdated($schedule, $replacementInstructor, $user, $leave->id);

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
            }, 3);
        } catch (QueryException $e) {
            $this->handleConcurrencyQueryException($e);
        }
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

        try {
            return DB::transaction(function () use ($leaveId, $scheduleId, $trimmedReason) {
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

                // 1. Verifikasi relasi jadwal dengan izin dan pastikan tidak dibatalkan (cancelled)
                $this->validateScheduleBelongsToLeave($schedule, $leave);

                // 2. Cegah keputusan ganda (idempotency check di dalam transaksi)
                $existingSub = ScheduleSubstitution::where('instructor_leave_id', $leaveId)
                    ->where('schedule_id', $scheduleId)
                    ->first();

                if ($existingSub && $existingSub->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'schedule_id' => 'Jadwal ini sudah memiliki keputusan persetujuan dan tidak dapat diubah kembali.',
                    ]);
                }

                // 3. Simpan penolakan dalam DB transaction (Jadwal kelas TIDAK diubah sama sekali)
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

                // Catat aktivitas penolakan secara atomik di dalam transaksi
                app(ActivityLogService::class)->logRejection($substitution, $schedule, $trimmedReason, $user);

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
            }, 3);
        } catch (QueryException $e) {
            $this->handleConcurrencyQueryException($e);
        }
    }

    /**
     * Menangani QueryException akibat race condition atau penguncian SQLite.
     *
     * @throws ValidationException|QueryException
     */
    protected function handleConcurrencyQueryException(QueryException $e): never
    {
        $message = strtolower($e->getMessage());

        if (
            str_contains($message, 'unique constraint failed') ||
            str_contains($message, 'duplicate entry') ||
            str_contains($message, 'uq_leave_schedule_sub') ||
            $e->getCode() === '23000'
        ) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal ini sedang atau sudah diproses oleh permintaan lain. Silakan muat ulang halaman.',
            ]);
        }

        if (
            str_contains($message, 'database is locked') ||
            str_contains($message, 'table is locked') ||
            str_contains($message, 'busy') ||
            $e->getCode() === 'HY000' ||
            ($e->errorInfo[1] ?? null) === 5
        ) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Sistem sedang sibuk memproses transaksi lain pada database. Silakan muat ulang halaman dan coba kembali.',
            ]);
        }

        throw $e;
    }

    /**
     * Memverifikasi apakah jadwal kelas benar-benar terasosiasi dan terdampak oleh izin instruktur.
     */
    protected function validateScheduleBelongsToLeave(Schedule $schedule, InstructorLeave $leave): void
    {
        // 1. Blokir keputusan untuk jadwal yang berstatus cancelled
        if ($schedule->status === 'cancelled') {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal kelas telah dibatalkan dan tidak dapat disetujui maupun ditolak.',
            ]);
        }

        $scheduleDate = Carbon::parse($schedule->date)->format('Y-m-d');
        $leaveDate = Carbon::parse($leave->date)->format('Y-m-d');

        if ($scheduleDate !== $leaveDate) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal kelas bukan merupakan bagian dari pengajuan izin yang dipilih.',
            ]);
        }

        // 2. Periksa tumpang tindih waktu (start_time < leave_end && end_time > leave_start)
        $scheduleStart = substr((string) $schedule->start_time, 0, 5);
        $scheduleEnd = substr((string) $schedule->end_time, 0, 5);
        $leaveStart = substr((string) $leave->start_time, 0, 5);
        $leaveEnd = substr((string) $leave->end_time, 0, 5);

        if ($scheduleStart >= $leaveEnd || $scheduleEnd <= $leaveStart) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal kelas bukan merupakan bagian dari pengajuan izin yang dipilih.',
            ]);
        }

        // 3. Instruktur jadwal harus sesuai instruktur izin, atau memiliki riwayat original_instructor_id yang sesuai
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
