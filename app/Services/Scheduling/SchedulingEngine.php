<?php

namespace App\Services\Scheduling;

use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\Scheduling\DTOs\CandidateEvaluation;
use App\Services\Scheduling\DTOs\RoomEvaluation;
use App\Services\Scheduling\DTOs\ScheduleEvaluation;
use App\Services\Scheduling\DTOs\SchedulingResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Deterministic Scheduling Engine for resolving instructor leave replacements.
 */
class SchedulingEngine
{
    /**
     * Skill level weights used for deterministic candidate ranking.
     */
    private const SKILL_WEIGHTS = [
        'advanced' => 100,
        'intermediate' => 75,
        'beginner' => 50,
    ];

    /**
     * Evaluate replacement candidates for an existing InstructorLeave record.
     */
    public function evaluateLeave(InstructorLeave $leave): SchedulingResult
    {
        $date = $this->normalizeDate($leave->date);

        return $this->evaluate(
            instructorId: $leave->instructor_id,
            date: $date,
            startTime: $leave->start_time,
            endTime: $leave->end_time,
            leaveId: $leave->id,
            leaveReason: $leave->reason,
        );
    }

    /**
     * Evaluate replacement candidates given instructor ID, date, and leave time window.
     */
    public function evaluate(
        int $instructorId,
        mixed $date,
        string $startTime,
        string $endTime,
        ?int $leaveId = null,
        ?string $leaveReason = null,
    ): SchedulingResult {
        $instructor = Instructor::findOrFail($instructorId);
        $dateString = $this->normalizeDate($date);
        $normalizedStart = $this->normalizeTime($startTime);
        $normalizedEnd = $this->normalizeTime($endTime);

        $affectedSchedules = $this->findAffectedSchedules($instructorId, $dateString, $normalizedStart, $normalizedEnd);

        $scheduleEvaluations = [];
        foreach ($affectedSchedules as $schedule) {
            $scheduleEvaluations[] = $this->evaluateSchedule($schedule, $instructorId);
        }

        $totalAffected = count($scheduleEvaluations);
        $totalResolved = count(array_filter($scheduleEvaluations, fn (ScheduleEvaluation $eval) => $eval->hasCandidate()));
        $allResolved = $totalAffected > 0 && $totalResolved === $totalAffected;

        $summary = $this->buildResultSummary($totalAffected, $totalResolved, $allResolved);

        return new SchedulingResult(
            leaveId: $leaveId,
            instructorId: $instructor->id,
            instructorName: $instructor->name,
            leaveDate: $dateString,
            leaveStartTime: $normalizedStart,
            leaveEndTime: $normalizedEnd,
            leaveReason: $leaveReason,
            affectedSchedules: $scheduleEvaluations,
            totalAffectedSchedules: $totalAffected,
            totalResolvedSchedules: $totalResolved,
            allSchedulesResolved: $allResolved,
            summary: $summary,
        );
    }

    /**
     * Find class schedules affected by instructor leave.
     *
     * @return Collection<int, Schedule>
     */
    public function findAffectedSchedules(
        int $instructorId,
        string $date,
        string $startTime,
        string $endTime,
    ): Collection {
        return Schedule::query()
            ->where('instructor_id', $instructorId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->with(['courseClass', 'room', 'instructor'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Evaluate replacement candidates and room for a single affected schedule.
     */
    public function evaluateSchedule(Schedule $schedule, ?int $excludedInstructorId = null): ScheduleEvaluation
    {
        $roomEvaluation = $schedule->room ? $this->evaluateRoom($schedule->room, $schedule) : null;

        $candidates = Instructor::query()
            ->where('status', 'active')
            ->when($excludedInstructorId, fn ($query) => $query->where('id', '!=', $excludedInstructorId))
            ->with(['skills', 'schedules.courseClass', 'leaves'])
            ->orderBy('name')
            ->get();

        $allEvaluations = [];
        $validCandidates = [];
        $disqualifiedCandidates = [];

        foreach ($candidates as $candidate) {
            $candidateEval = $this->evaluateCandidate($candidate, $schedule);
            $allEvaluations[] = $candidateEval;

            if ($candidateEval->isValid) {
                $validCandidates[] = $candidateEval;
            } else {
                $disqualifiedCandidates[] = $candidateEval;
            }
        }

        // Deterministic sort: highest score first, then name alphabetically as tie-breaker
        usort($validCandidates, function (CandidateEvaluation $a, CandidateEvaluation $b) {
            if ($b->score !== $a->score) {
                return $b->score <=> $a->score;
            }

            return strcmp($a->instructorName, $b->instructorName);
        });

        $bestCandidate = $validCandidates[0] ?? null;

        $status = ! empty($validCandidates) ? 'resolved' : 'no_candidate';
        $summary = ! empty($validCandidates)
            ? 'Ditemukan '.count($validCandidates)." kandidat pengganti yang valid. Rekomendasi utama: {$bestCandidate->instructorName} (Skor: {$bestCandidate->score})."
            : 'Belum ada instruktur pengganti yang memenuhi kualifikasi kompetensi dan bebas bentrok jadwal untuk kelas ini.';

        $scheduleDate = $this->normalizeDate($schedule->date);

        return new ScheduleEvaluation(
            scheduleId: $schedule->id,
            courseClassId: $schedule->course_class_id,
            className: $schedule->courseClass->name ?? 'Kelas #'.$schedule->course_class_id,
            subject: $schedule->courseClass->subject ?? '-',
            date: $scheduleDate,
            startTime: $schedule->start_time,
            endTime: $schedule->end_time,
            roomEvaluation: $roomEvaluation,
            status: $status,
            candidates: $allEvaluations,
            validCandidates: $validCandidates,
            disqualifiedCandidates: $disqualifiedCandidates,
            bestCandidate: $bestCandidate,
            summary: $summary,
        );
    }

    /**
     * Evaluate an individual candidate instructor for a specific schedule.
     */
    public function evaluateCandidate(Instructor $candidate, Schedule $schedule): CandidateEvaluation
    {
        $scheduleDate = $this->normalizeDate($schedule->date);
        $requiredSubject = mb_strtolower(trim($schedule->courseClass->subject ?? ''));
        $disqualifications = [];

        // 1. Check Active Status
        $isActive = $candidate->status === 'active';
        if (! $isActive) {
            $disqualifications[] = 'Status instruktur tidak aktif.';
        }

        // 2. Check Competency / Skills
        $matchingSkill = $candidate->skills->first(function ($skill) use ($requiredSubject) {
            return mb_strtolower(trim($skill->skill)) === $requiredSubject;
        });
        $competencyMatched = $matchingSkill !== null;
        if (! $competencyMatched) {
            $disqualifications[] = "Tidak memiliki keahlian atau kompetensi untuk mata pelajaran '{$schedule->courseClass->subject}'.";
        }

        // 3. Check Schedule Conflicts
        $conflictSchedule = $this->findConflictingSchedule(
            $candidate,
            $scheduleDate,
            $schedule->start_time,
            $schedule->end_time,
            $schedule->id
        );
        $hasScheduleConflict = $conflictSchedule !== null;
        if ($hasScheduleConflict) {
            $conflictClassName = $conflictSchedule->courseClass->name ?? 'Kelas Lain';
            $disqualifications[] = "Jadwal bentrok dengan '{$conflictClassName}' pada jam {$conflictSchedule->start_time} - {$conflictSchedule->end_time}.";
        }

        // 4. Check Leave Conflicts
        $conflictLeave = $this->findConflictingLeave(
            $candidate,
            $scheduleDate,
            $schedule->start_time,
            $schedule->end_time
        );
        $hasLeaveConflict = $conflictLeave !== null;
        if ($hasLeaveConflict) {
            $disqualifications[] = "Instruktur sedang mengajukan izin pada jam {$conflictLeave->start_time} - {$conflictLeave->end_time}.";
        }

        $isValid = $isActive && $competencyMatched && ! $hasScheduleConflict && ! $hasLeaveConflict;
        $otherClassesCount = $this->countOtherClassesToday($candidate, $scheduleDate, $schedule->id);

        if (! $isValid) {
            return new CandidateEvaluation(
                instructorId: $candidate->id,
                instructorName: $candidate->name,
                isValid: false,
                competencyMatched: $competencyMatched,
                skillName: $matchingSkill?->skill,
                skillLevel: $matchingSkill?->level,
                hasScheduleConflict: $hasScheduleConflict,
                hasLeaveConflict: $hasLeaveConflict,
                score: 0,
                reasons: $disqualifications,
                disqualificationReason: implode(' ', $disqualifications),
                otherClassesCountToday: $otherClassesCount,
            );
        }

        // Candidate is Valid! Calculate score & reasons
        $score = $this->calculateCandidateScore($matchingSkill->level, $otherClassesCount);

        $reasons = [
            "Memiliki kompetensi {$matchingSkill->skill} (Tingkat: ".ucfirst($matchingSkill->level).').',
            "Jadwal mengajar kosong pada pukul {$schedule->start_time} - {$schedule->end_time}.",
            'Tidak ada pengajuan izin pada jam tersebut.',
        ];

        if ($otherClassesCount === 0) {
            $reasons[] = 'Tidak ada jadwal mengajar lain pada hari ini (beban mengajar ringan).';
        } else {
            $reasons[] = "Memiliki {$otherClassesCount} jadwal mengajar lain pada hari ini.";
        }

        return new CandidateEvaluation(
            instructorId: $candidate->id,
            instructorName: $candidate->name,
            isValid: true,
            competencyMatched: true,
            skillName: $matchingSkill->skill,
            skillLevel: $matchingSkill->level,
            hasScheduleConflict: false,
            hasLeaveConflict: false,
            score: $score,
            reasons: $reasons,
            otherClassesCountToday: $otherClassesCount,
        );
    }

    /**
     * Evaluate room suitability and schedule conflict for an affected schedule.
     */
    public function evaluateRoom(Room $room, Schedule $schedule): RoomEvaluation
    {
        $scheduleDate = $this->normalizeDate($schedule->date);
        $studentCount = $schedule->courseClass->student_count ?? 0;
        $capacity = $room->capacity;

        $isCapacitySufficient = $capacity >= $studentCount;
        $isStatusAvailable = mb_strtolower(trim($room->status)) === 'available';

        // Check whether another class is using this room at the same time
        $roomConflict = Schedule::query()
            ->where('room_id', $room->id)
            ->where('id', '!=', $schedule->id)
            ->whereDate('date', $scheduleDate)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $this->normalizeTime($schedule->end_time))
            ->where('end_time', '>', $this->normalizeTime($schedule->start_time))
            ->with('courseClass')
            ->first();

        $hasRoomConflict = $roomConflict !== null;
        $isValid = $isCapacitySufficient && $isStatusAvailable && ! $hasRoomConflict;

        $notes = [];
        if (! $isCapacitySufficient) {
            $notes[] = "Kapasitas ruangan ({$capacity}) kurang dari jumlah siswa ({$studentCount}).";
        }
        if (! $isStatusAvailable) {
            $notes[] = "Status ruangan saat ini adalah '{$room->status}'.";
        }
        if ($hasRoomConflict) {
            $notes[] = "Ruangan bentrok dengan kelas '{$roomConflict->courseClass?->name}' pada jam {$roomConflict->start_time} - {$roomConflict->end_time}.";
        }
        if ($isValid) {
            $notes[] = "Ruangan {$room->name} siap digunakan (Kapasitas: {$capacity}, Siswa: {$studentCount}).";
        }

        return new RoomEvaluation(
            roomId: $room->id,
            roomName: $room->name,
            capacity: $capacity,
            studentCount: $studentCount,
            isCapacitySufficient: $isCapacitySufficient,
            isStatusAvailable: $isStatusAvailable,
            hasRoomConflict: $hasRoomConflict,
            isValid: $isValid,
            notes: implode(' ', $notes),
        );
    }

    /**
     * Check if a candidate instructor has an overlapping schedule.
     */
    protected function findConflictingSchedule(
        Instructor $candidate,
        string $date,
        string $startTime,
        string $endTime,
        ?int $ignoreScheduleId = null,
    ): ?Schedule {
        if ($candidate->relationLoaded('schedules')) {
            return $candidate->schedules->first(function (Schedule $sched) use ($date, $startTime, $endTime, $ignoreScheduleId) {
                if ($ignoreScheduleId && $sched->id === $ignoreScheduleId) {
                    return false;
                }
                if ($sched->status === 'cancelled') {
                    return false;
                }
                $schedDate = $this->normalizeDate($sched->date);
                if ($schedDate !== $date) {
                    return false;
                }

                return $this->intervalsOverlap($sched->start_time, $sched->end_time, $startTime, $endTime);
            });
        }

        return Schedule::query()
            ->where('instructor_id', $candidate->id)
            ->when($ignoreScheduleId, fn ($query) => $query->where('id', '!=', $ignoreScheduleId))
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $this->normalizeTime($endTime))
            ->where('end_time', '>', $this->normalizeTime($startTime))
            ->with('courseClass')
            ->first();
    }

    /**
     * Check if a candidate instructor has an active leave during the requested time.
     */
    protected function findConflictingLeave(
        Instructor $candidate,
        string $date,
        string $startTime,
        string $endTime,
    ): ?InstructorLeave {
        if ($candidate->relationLoaded('leaves')) {
            return $candidate->leaves->first(function (InstructorLeave $leave) use ($date, $startTime, $endTime) {
                if ($leave->status === 'rejected') {
                    return false;
                }
                $leaveDate = $this->normalizeDate($leave->date);
                if ($leaveDate !== $date) {
                    return false;
                }

                return $this->intervalsOverlap($leave->start_time, $leave->end_time, $startTime, $endTime);
            });
        }

        return InstructorLeave::query()
            ->where('instructor_id', $candidate->id)
            ->where('status', '!=', 'rejected')
            ->whereDate('date', $date)
            ->where('start_time', '<', $this->normalizeTime($endTime))
            ->where('end_time', '>', $this->normalizeTime($startTime))
            ->first();
    }

    /**
     * Count other classes taught by candidate on the given date.
     */
    protected function countOtherClassesToday(
        Instructor $candidate,
        string $date,
        ?int $ignoreScheduleId = null,
    ): int {
        if ($candidate->relationLoaded('schedules')) {
            return $candidate->schedules->filter(function (Schedule $sched) use ($date, $ignoreScheduleId) {
                if ($ignoreScheduleId && $sched->id === $ignoreScheduleId) {
                    return false;
                }
                if ($sched->status === 'cancelled') {
                    return false;
                }
                $schedDate = $this->normalizeDate($sched->date);

                return $schedDate === $date;
            })->count();
        }

        return Schedule::query()
            ->where('instructor_id', $candidate->id)
            ->when($ignoreScheduleId, fn ($query) => $query->where('id', '!=', $ignoreScheduleId))
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    /**
     * Normalize date value to Y-m-d format.
     */
    public function normalizeDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse((string) $date)->format('Y-m-d');
    }

    /**
     * Calculate deterministic candidate score based on skill level and workload.
     */
    protected function calculateCandidateScore(?string $skillLevel, int $otherClassesCountToday): int
    {
        $normalizedLevel = mb_strtolower(trim($skillLevel ?? ''));
        $baseScore = self::SKILL_WEIGHTS[$normalizedLevel] ?? 40;

        // Workload bonus: instructors with fewer commitments today get a higher bonus
        $workloadBonus = match ($otherClassesCountToday) {
            0 => 20,
            1 => 10,
            default => 0,
        };

        return $baseScore + $workloadBonus;
    }

    /**
     * Check if two time intervals overlap (assuming standard HH:mm:ss format).
     */
    public function intervalsOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        $s1 = $this->normalizeTime($start1);
        $e1 = $this->normalizeTime($end1);
        $s2 = $this->normalizeTime($start2);
        $e2 = $this->normalizeTime($end2);

        return $s1 < $e2 && $e1 > $s2;
    }

    /**
     * Normalize time strings into HH:mm:ss format.
     */
    public function normalizeTime(string $time): string
    {
        $parts = explode(':', trim($time));
        $hours = str_pad($parts[0] ?? '00', 2, '0', STR_PAD_LEFT);
        $minutes = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);
        $seconds = str_pad($parts[2] ?? '00', 2, '0', STR_PAD_LEFT);

        return "{$hours}:{$minutes}:{$seconds}";
    }

    /**
     * Build summary message for evaluation result.
     */
    private function buildResultSummary(int $totalAffected, int $totalResolved, bool $allResolved): string
    {
        if ($totalAffected === 0) {
            return 'Tidak ada jadwal kelas yang terdampak pada rentang waktu izin ini.';
        }

        if ($allResolved) {
            return "Berhasil menemukan kandidat instruktur pengganti yang valid untuk seluruh {$totalAffected} kelas yang terdampak.";
        }

        if ($totalResolved > 0) {
            return "Ditemukan pengganti untuk {$totalResolved} dari {$totalAffected} kelas. Terdapat kelas yang belum memiliki kandidat valid.";
        }

        return "Belum ada instruktur pengganti yang memenuhi syarat untuk {$totalAffected} kelas yang terdampak.";
    }
}
