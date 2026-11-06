<?php

namespace App\Services\Scheduling;

use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleApprovalDecision;
use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\User;
use App\Services\Room\RoomConflictGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service untuk memvalidasi ulang potensi bentrok jadwal secara real-time
 * tepat sebelum keputusan persetujuan BAAK disahkan dan ditulis ke tabel schedules.
 */
class ProposalApprovalConflictValidator
{
    public function __construct(
        protected RoomConflictGuard $roomGuard
    ) {}

    /**
     * Memeriksa seluruh slot jadwal pada opsi proposal terpilih terhadap ketersediaan
     * terkini instruktur, ruangan, dan cuti aktif di basis data operasional.
     *
     * @return array{has_conflicts: bool, conflicts: list<array{type: string, date: string, start_time: string, end_time: string, resource_id: int, resource_name: string, reason: string}>}
     *
     * @throws ValidationException Jika ditemukan bentrok fatal dan mode fail-closed aktif.
     */
    public function validateOption(ScheduleProposal $proposal, ScheduleProposalOption $option, bool $failClosed = true): array
    {
        // 1. Verifikasi integritas relasi: Opsi wajib milik proposal ini
        if ($option->proposal_id !== $proposal->id) {
            throw ValidationException::withMessages([
                'option' => "Opsi #{$option->id} tidak valid untuk proposal #{$proposal->id}.",
            ]);
        }

        $proposedSlots = $option->proposed_schedules_payload;
        if (! is_array($proposedSlots) || empty($proposedSlots)) {
            throw ValidationException::withMessages([
                'proposed_schedules_payload' => 'Payload rencana jadwal pada opsi ini kosong atau tidak berformat valid.',
            ]);
        }

        $conflicts = [];

        foreach ($proposedSlots as $index => $slot) {
            $date = $slot['date'] ?? null;
            $startTime = $this->normalizeApprovalTime($slot['start_time'] ?? null);
            $endTime = $this->normalizeApprovalTime($slot['end_time'] ?? null);
            $instructorId = $slot['instructor_id'] ?? null;
            $roomId = $slot['room_id'] ?? null;

            if (! $date || ! $startTime || ! $endTime) {
                $conflicts[] = [
                    'type' => 'malformed_slot',
                    'date' => (string) $date,
                    'start_time' => (string) ($slot['start_time'] ?? ''),
                    'end_time' => (string) ($slot['end_time'] ?? ''),
                    'resource_id' => 0,
                    'resource_name' => "Slot #{$index}",
                    'reason' => 'Parameter waktu jadwal tidak lengkap.',
                ];

                continue;
            }

            if (! $this->validResourceId($roomId)) {
                $conflicts[] = [
                    'type' => 'room_missing',
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'resource_id' => 0,
                    'resource_name' => 'Slot #'.($index + 1),
                    'reason' => 'Slot proposal belum memiliki room_id yang valid.',
                ];
            }

            if (! $this->validResourceId($instructorId)) {
                $conflicts[] = [
                    'type' => 'instructor_missing',
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'resource_id' => 0,
                    'resource_name' => 'Slot #'.($index + 1),
                    'reason' => 'Slot proposal belum memiliki instructor_id yang valid.',
                ];
            }

            // A. Validasi Ruangan (Status, Kapasitas, Bidang, Fasilitas, dan Bentrok Jadwal)
            if ($this->validResourceId($roomId)) {
                $room = Room::find($roomId);
                if (! $room) {
                    $conflicts[] = [
                        'type' => 'room_not_found',
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'resource_id' => $roomId,
                        'resource_name' => "Ruangan #{$roomId}",
                        'reason' => 'Ruangan tidak ditemukan di database.',
                    ];
                } elseif (mb_strtolower(trim($room->status)) !== 'available') {
                    $conflicts[] = [
                        'type' => 'room_unavailable',
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'resource_id' => $roomId,
                        'resource_name' => $room->name,
                        'reason' => "Status ruangan saat ini adalah '{$room->status}' (bukan available).",
                    ];
                } elseif ($proposal->student_count > $room->capacity) {
                    $conflicts[] = [
                        'type' => 'room_capacity_exceeded',
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'resource_id' => $roomId,
                        'resource_name' => $room->name,
                        'reason' => "Kapasitas ruangan ({$room->capacity}) lebih kecil dari jumlah siswa ({$proposal->student_count}).",
                    ];
                } elseif ($room->department && $proposal->department && $room->department !== $proposal->department) {
                    $conflicts[] = [
                        'type' => 'room_department_mismatch',
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'resource_id' => $roomId,
                        'resource_name' => $room->name,
                        'reason' => "Bidang ruangan ({$room->department}) tidak sesuai dengan bidang proposal ({$proposal->department}).",
                    ];
                } else {
                    // Validasi Pemenuhan Fasilitas Ruangan
                    if (! empty($proposal->required_facility_codes)) {
                        $availableCodes = $room->facilities()
                            ->wherePivot('condition', '!=', 'maintenance')
                            ->pluck('code')
                            ->toArray();
                        $missing = array_diff($proposal->required_facility_codes, $availableCodes);
                        if (! empty($missing)) {
                            $conflicts[] = [
                                'type' => 'room_missing_facilities',
                                'date' => $date,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'resource_id' => $roomId,
                                'resource_name' => $room->name,
                                'reason' => 'Ruangan tidak memiliki fasilitas yang dibutuhkan: '.implode(', ', $missing),
                            ];
                        }
                    }

                    // Cek jadwal resmi yang bentrok pada ruangan ini
                    $roomOverlap = Schedule::query()
                        ->where('room_id', $roomId)
                        ->whereDate('date', $date)
                        ->whereIn('status', RoomConflictGuard::OFFICIAL_STATUSES)
                        ->where(function ($query) use ($startTime, $endTime): void {
                            $query->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        })
                        ->first();

                    if ($roomOverlap) {
                        $conflicts[] = [
                            'type' => 'room_double_booking',
                            'date' => $date,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'resource_id' => $roomId,
                            'resource_name' => $room->name,
                            'reason' => "Ruangan telah terisi oleh jadwal resmi lain (ID #{$roomOverlap->id}) pada jam tersebut.",
                        ];
                    }
                }
            }

            // B. Validasi Instruktur (Status, Bidang, Kompetensi, Ketersediaan, Cuti, dan Bentrok Jadwal)
            if ($this->validResourceId($instructorId)) {
                $instructor = Instructor::find($instructorId);
                if (! $instructor) {
                    $conflicts[] = [
                        'type' => 'instructor_not_found',
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'resource_id' => $instructorId,
                        'resource_name' => "Instruktur #{$instructorId}",
                        'reason' => 'Instruktur tidak ditemukan di database.',
                    ];
                } elseif (mb_strtolower(trim($instructor->status)) !== 'active') {
                    $conflicts[] = [
                        'type' => 'instructor_inactive',
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'resource_id' => $instructorId,
                        'resource_name' => $instructor->name,
                        'reason' => "Status instruktur saat ini adalah '{$instructor->status}' (bukan active).",
                    ];
                } elseif ($instructor->department && $proposal->department && $instructor->department !== $proposal->department) {
                    $conflicts[] = [
                        'type' => 'instructor_department_mismatch',
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'resource_id' => $instructorId,
                        'resource_name' => $instructor->name,
                        'reason' => "Bidang instruktur ({$instructor->department}) tidak sesuai dengan bidang proposal ({$proposal->department}).",
                    ];
                } else {
                    // Validasi Kesesuaian Kompetensi Instruktur
                    if (! empty($proposal->required_competency_ids)) {
                        $instructorCompetencies = $instructor->skills()
                            ->whereNotNull('competency_id')
                            ->pluck('competency_id')
                            ->toArray();
                        $missingCompetencies = array_diff($proposal->required_competency_ids, $instructorCompetencies);
                        if (! empty($missingCompetencies)) {
                            $conflicts[] = [
                                'type' => 'instructor_missing_competency',
                                'date' => $date,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'resource_id' => $instructorId,
                                'resource_name' => $instructor->name,
                                'reason' => 'Instruktur belum memiliki kompetensi yang disyaratkan (ID: '.implode(', ', $missingCompetencies).').',
                            ];
                        }
                    }

                    // Validasi Jadwal Ketersediaan Instruktur (Availability Slots)
                    if ($instructor->availabilities()->exists()) {
                        $dayOfWeekIso = Carbon::parse($date)->dayOfWeekIso;
                        $dayLabels = match ($dayOfWeekIso) {
                            1 => ['Senin', 'Monday', 'senin', 'monday', '1'],
                            2 => ['Selasa', 'Tuesday', 'selasa', 'tuesday', '2'],
                            3 => ['Rabu', 'Wednesday', 'rabu', 'wednesday', '3'],
                            4 => ['Kamis', 'Thursday', 'kamis', 'thursday', '4'],
                            5 => ['Jumat', 'Friday', 'jumat', 'friday', '5'],
                            6 => ['Sabtu', 'Saturday', 'sabtu', 'saturday', '6'],
                            default => ['Minggu', 'Sunday', 'minggu', 'sunday', '7'],
                        };

                        $hasSlot = $instructor->availabilities()
                            ->whereIn('day', $dayLabels)
                            ->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime)
                            ->exists();

                        if (! $hasSlot) {
                            $conflicts[] = [
                                'type' => 'instructor_unavailable_day_time',
                                'date' => $date,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'resource_id' => $instructorId,
                                'resource_name' => $instructor->name,
                                'reason' => 'Instruktur tidak memiliki slot ketersediaan mengajar pada hari/jam tersebut.',
                            ];
                        }
                    }

                    // Cek cuti aktif instruktur pada rentang waktu ini
                    $leaveConflict = InstructorLeave::query()
                        ->where('instructor_id', $instructorId)
                        ->whereDate('date', $date)
                        ->where('status', '!=', 'rejected')
                        ->where(function ($query) use ($startTime, $endTime): void {
                            $query->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        })
                        ->first();

                    if ($leaveConflict) {
                        $conflicts[] = [
                            'type' => 'instructor_on_leave',
                            'date' => $date,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'resource_id' => $instructorId,
                            'resource_name' => $instructor->name,
                            'reason' => "Instruktur sedang mengajukan atau memiliki cuti aktif (ID #{$leaveConflict->id}) pada jam tersebut.",
                        ];
                    }

                    // Cek jadwal resmi lain instruktur
                    $instructorOverlap = Schedule::query()
                        ->where('instructor_id', $instructorId)
                        ->whereDate('date', $date)
                        ->whereIn('status', RoomConflictGuard::OFFICIAL_STATUSES)
                        ->where(function ($query) use ($startTime, $endTime): void {
                            $query->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        })
                        ->first();

                    if ($instructorOverlap) {
                        $conflicts[] = [
                            'type' => 'instructor_double_booking',
                            'date' => $date,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'resource_id' => $instructorId,
                            'resource_name' => $instructor->name,
                            'reason' => "Instruktur telah dijadwalkan mengajar pada kelas lain (ID #{$instructorOverlap->id}) pada jam tersebut.",
                        ];
                    }
                }
            }
        }

        $hasConflicts = ! empty($conflicts);

        if ($hasConflicts && $failClosed) {
            $first = $conflicts[0];
            throw ValidationException::withMessages([
                'approval' => "Persetujuan proposal ditolak karena ditemukan bentrok terkini pada {$first['date']} ({$first['resource_name']}): {$first['reason']}",
            ]);
        }

        return [
            'has_conflicts' => $hasConflicts,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Mengeksekusi proses persetujuan BAAK secara atomik dengan perlindungan konkurensi (pessimistic locking)
     * dan token idempoten untuk mencegah double-booking pada race condition.
     *
     * @throws ValidationException
     */
    public function executeApprovalWithLock(
        ScheduleProposal $proposal,
        ScheduleProposalOption $option,
        User $baakUser,
        string $idempotencyToken,
        ?string $reason = null
    ): ScheduleApprovalDecision {
        $this->assertBaak($baakUser);

        return DB::transaction(function () use ($proposal, $option, $baakUser, $idempotencyToken, $reason): ScheduleApprovalDecision {
            // 1. Guard Idempotensi: Jika token keputusan ini sudah diproses sebelumnya, kembalikan record keputusan
            $existingDecision = ScheduleApprovalDecision::where('idempotency_token', $idempotencyToken)->first();
            if ($existingDecision) {
                if ($existingDecision->proposal_id !== $proposal->id || $existingDecision->decision !== 'approved') {
                    throw ValidationException::withMessages([
                        'idempotency_token' => 'Token idempotensi telah digunakan untuk keputusan lain.',
                    ]);
                }

                return $existingDecision;
            }

            // 2. Kunci baris proposal untuk isolasi konkurensi
            $lockedProposal = ScheduleProposal::lockForUpdate()->findOrFail($proposal->id);
            if ($lockedProposal->status !== 'pending') {
                throw ValidationException::withMessages([
                    'proposal' => 'Proposal ini sudah diproses dan tidak lagi berstatus pending.',
                ]);
            }

            $lockedOption = ScheduleProposalOption::query()
                ->where('proposal_id', $lockedProposal->id)
                ->lockForUpdate()
                ->findOrFail($option->id);
            if ($lockedProposal->selected_option_id !== $lockedOption->id) {
                throw ValidationException::withMessages([
                    'option' => 'Opsi approval bukan opsi terpilih pada proposal.',
                ]);
            }

            // 3. Kunci seluruh resource (ruangan & instruktur) yang terlibat pada opsi proposal ini
            $proposedSlots = (array) $lockedOption->proposed_schedules_payload;
            $roomIds = array_values(array_filter(array_unique(array_column($proposedSlots, 'room_id')), $this->validResourceId(...)));
            $instructorIds = array_values(array_filter(array_unique(array_column($proposedSlots, 'instructor_id')), $this->validResourceId(...)));

            if (! empty($roomIds)) {
                Room::whereIn('id', $roomIds)->lockForUpdate()->get();
            }
            if (! empty($instructorIds)) {
                Instructor::whereIn('id', $instructorIds)->lockForUpdate()->get();
            }

            // 4. Validasi ulang bentrok secara real-time di dalam transaksi yang terkunci
            $this->validateOption($lockedProposal, $lockedOption, failClosed: true);

            // 5. Hitung nomor putaran berikutnya
            $nextRound = ((int) $lockedProposal->decisions()->max('round')) + 1;

            // 6. Buat record keputusan persetujuan BAAK
            $decision = ScheduleApprovalDecision::create([
                'proposal_id' => $lockedProposal->id,
                'round' => $nextRound,
                'decision_by' => $baakUser->id,
                'decision' => 'approved',
                'reason' => $reason,
                'idempotency_token' => $idempotencyToken,
                'decided_at' => Carbon::now(),
            ]);

            // 7. Update status proposal dan opsi terpilih
            $lockedProposal->selectOption($lockedOption);
            $lockedProposal->update([
                'status' => 'approved',
            ]);

            // 8. Tulis jadwal pertemuan resmi ke tabel schedules
            foreach ($proposedSlots as $seq => $slot) {
                Schedule::create([
                    'course_class_id' => $lockedProposal->course_class_id ?? CourseClass::firstOrCreate(
                        ['name' => $lockedProposal->title],
                        [
                            'subject' => $lockedProposal->title,
                            'student_count' => $lockedProposal->student_count,
                            'program_id' => $lockedProposal->program_id,
                            'department' => $lockedProposal->department,
                            'status' => 'active',
                        ]
                    )->id,
                    'schedule_proposal_id' => $lockedProposal->id,
                    'curriculum_subject_id' => $slot['curriculum_subject_id'] ?? null,
                    'meeting_sequence' => $seq + 1,
                    'instructor_id' => (int) $slot['instructor_id'],
                    'room_id' => (int) $slot['room_id'],
                    'date' => $slot['date'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'status' => 'scheduled',
                ]);
            }

            return $decision;
        });
    }

    /**
     * Menolak proposal secara atomik tanpa membuat jadwal resmi.
     *
     * @throws ValidationException
     */
    public function executeRejectionWithLock(
        ScheduleProposal $proposal,
        User $baakUser,
        string $idempotencyToken,
        string $reason
    ): ScheduleApprovalDecision {
        $this->assertBaak($baakUser);
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($proposal, $baakUser, $idempotencyToken, $reason): ScheduleApprovalDecision {
            $existingDecision = ScheduleApprovalDecision::where('idempotency_token', $idempotencyToken)->first();
            if ($existingDecision) {
                if ($existingDecision->proposal_id !== $proposal->id || $existingDecision->decision !== 'rejected') {
                    throw ValidationException::withMessages([
                        'idempotency_token' => 'Token idempotensi telah digunakan untuk keputusan lain.',
                    ]);
                }

                return $existingDecision;
            }

            $lockedProposal = ScheduleProposal::lockForUpdate()->findOrFail($proposal->id);
            if ($lockedProposal->status !== 'pending') {
                throw ValidationException::withMessages([
                    'proposal' => 'Proposal ini sudah diproses dan tidak lagi berstatus pending.',
                ]);
            }

            $nextRound = ((int) $lockedProposal->decisions()->max('round')) + 1;
            $decision = ScheduleApprovalDecision::create([
                'proposal_id' => $lockedProposal->id,
                'round' => $nextRound,
                'decision_by' => $baakUser->id,
                'decision' => 'rejected',
                'reason' => $reason,
                'idempotency_token' => $idempotencyToken,
                'decided_at' => Carbon::now(),
            ]);

            $lockedProposal->update(['status' => 'rejected']);

            return $decision;
        });
    }

    private function validResourceId(mixed $resourceId): bool
    {
        return filter_var($resourceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    private function normalizeApprovalTime(mixed $time): ?string
    {
        if (! is_string($time)) {
            return null;
        }

        $time = trim($time);
        if (preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d\z/D', $time) === 1) {
            return $time;
        }

        if (preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/D', $time) === 1) {
            return $time.':00';
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    private function assertBaak(User $user): void
    {
        if (! $user->isBaak()) {
            throw ValidationException::withMessages([
                'authorization' => 'Hanya BAAK yang dapat memproses proposal kelas baru.',
            ]);
        }
    }
}
