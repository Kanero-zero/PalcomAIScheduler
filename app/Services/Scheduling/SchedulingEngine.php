<?php

namespace App\Services\Scheduling;

use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Services\ActivityLog\ActivityLogService;
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
     * Evaluate leave deterministically and enhance recommendations with Google Gemini AI.
     */
    public function evaluateWithAi(InstructorLeave $leave): SchedulingResult
    {
        $deterministicResult = $this->evaluateLeave($leave);

        $enhancedResult = app(GeminiSchedulingAdvisor::class)->enhanceEvaluation($deterministicResult);

        if ($enhancedResult->aiSummary) {
            app(ActivityLogService::class)->logAiRecommendation($enhancedResult, $enhancedResult->aiSummary);
        }

        return $enhancedResult;
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

        $affectedSchedules = $this->findAffectedSchedules($instructorId, $dateString, $normalizedStart, $normalizedEnd, $leaveId);

        $scheduleEvaluations = [];
        foreach ($affectedSchedules as $schedule) {
            $sub = $leaveId && $schedule->relationLoaded('substitutions')
                ? $schedule->substitutions->firstWhere('instructor_leave_id', $leaveId)
                : null;
            $excludeId = $sub?->original_instructor_id ?? $instructorId;
            $scheduleEvaluations[] = $this->evaluateSchedule($schedule, $excludeId, $leaveId, $sub);
        }

        // Resolve potential cross-schedule double booking when multiple affected classes overlap
        $scheduleEvaluations = $this->resolveCrossScheduleConflicts($scheduleEvaluations);

        $totalAffected = count($scheduleEvaluations);
        $totalResolved = count(array_filter($scheduleEvaluations, fn (ScheduleEvaluation $eval) => $eval->isResolved()));
        $allResolved = $totalAffected > 0 && $totalResolved === $totalAffected;

        $summary = $this->buildResultSummary($totalAffected, $totalResolved, $allResolved);

        $result = new SchedulingResult(
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

        if ($leaveId) {
            app(ActivityLogService::class)->logEngineEvaluation($result);
        }

        return $result;
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
        ?int $leaveId = null,
    ): Collection {
        return Schedule::query()
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where(function ($query) use ($instructorId, $leaveId) {
                $query->where('instructor_id', $instructorId);

                if ($leaveId !== null) {
                    $query->orWhereHas('substitutions', function ($subQuery) use ($leaveId) {
                        $subQuery->where('instructor_leave_id', $leaveId);
                    });
                }
            })
            ->with([
                'courseClass',
                'room',
                'instructor',
                'substitutions' => function ($q) use ($leaveId) {
                    if ($leaveId !== null) {
                        $q->where('instructor_leave_id', $leaveId);
                    }
                },
                'substitutions.originalInstructor',
                'substitutions.replacementInstructor',
                'substitutions.decisionMaker',
            ])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Evaluate replacement candidates and room for a single affected schedule.
     */
    public function evaluateSchedule(
        Schedule $schedule,
        ?int $excludedInstructorId = null,
        ?int $leaveId = null,
        ?ScheduleSubstitution $substitution = null,
    ): ScheduleEvaluation {
        if ($substitution === null && $leaveId !== null) {
            $substitution = $schedule->relationLoaded('substitutions')
                ? $schedule->substitutions->firstWhere('instructor_leave_id', $leaveId)
                : ScheduleSubstitution::where('instructor_leave_id', $leaveId)->where('schedule_id', $schedule->id)->first();
        }

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

        $hasCandidates = ! empty($validCandidates);
        $hasUsableRoom = $roomEvaluation !== null && $roomEvaluation->hasUsableRoom();
        $requiresRoomChange = $roomEvaluation !== null && $roomEvaluation->requiresRoomChange;

        if ($hasCandidates && $hasUsableRoom) {
            $status = 'resolved';
            if ($requiresRoomChange && $roomEvaluation->suggestedAlternativeRoom) {
                $summary = 'Ditemukan '.count($validCandidates)." kandidat pengganti. Ruangan awal bermasalah ({$roomEvaluation->roomName}), disarankan dialihkan ke {$roomEvaluation->suggestedAlternativeRoom->roomName}. Rekomendasi utama: {$bestCandidate->instructorName} (Skor: {$bestCandidate->score}).";
            } else {
                $summary = 'Ditemukan '.count($validCandidates)." kandidat pengganti yang valid. Rekomendasi utama: {$bestCandidate->instructorName} (Skor: {$bestCandidate->score}). Ruangan siap.";
            }
        } elseif ($hasCandidates && ! $hasUsableRoom) {
            $status = 'room_issue';
            $roomNotes = $roomEvaluation ? $roomEvaluation->notes : 'Jadwal tidak memiliki alokasi ruangan yang valid.';
            $summary = 'Ditemukan '.count($validCandidates)." kandidat pengganti, namun ruangan bermasalah: {$roomNotes}";
        } elseif (! $hasCandidates && $hasUsableRoom) {
            $status = 'no_candidate';
            $summary = 'Belum ada instruktur pengganti yang memenuhi kualifikasi kompetensi dan bebas bentrok jadwal untuk kelas ini.';
        } else {
            $status = 'unresolved';
            $summary = 'Belum ada instruktur pengganti yang memenuhi syarat dan ruangan tidak tersedia.';
        }

        // Resolve approval status
        $approvalStatus = null;
        if ($substitution !== null) {
            $isApproved = $substitution->status === 'approved';
            $isRejected = $substitution->status === 'rejected';

            $approvalStatus = [
                'is_decided' => $isApproved || $isRejected,
                'status' => $substitution->status,
                'substitution_id' => $substitution->id,
                'original_instructor_id' => $substitution->original_instructor_id,
                'original_instructor_name' => $substitution->originalInstructor?->name ?? $schedule->instructor?->name,
                'replacement_instructor_id' => $substitution->replacement_instructor_id,
                'replacement_instructor_name' => $substitution->replacementInstructor?->name,
                'decision_by' => $substitution->decision_by,
                'decision_by_name' => $substitution->decisionMaker?->name,
                'decision_at' => $substitution->decision_at?->toIso8601String(),
                'rejection_reason' => $substitution->rejection_reason,
                'notes' => $substitution->notes,
            ];

            if ($isApproved) {
                $status = 'resolved';
                $assignedName = $substitution->replacementInstructor?->name ?? 'Instruktur Pengganti';
                $summary = "Persetujuan final: Instruktur pengganti {$assignedName} telah disetujui dan ditugaskan.";

                // Pastikan bestCandidate merefleksikan instruktur pengganti yang disetujui jika ada
                if ($substitution->replacement_instructor_id) {
                    foreach ($allEvaluations as $candidateEval) {
                        if ($candidateEval->instructorId === $substitution->replacement_instructor_id) {
                            $bestCandidate = $candidateEval;
                            break;
                        }
                    }
                }
            } elseif ($isRejected) {
                $status = 'attention_needed';
                $summary = "Rekomendasi instruktur pengganti ditolak: {$substitution->rejection_reason}";
            }
        } elseif ($excludedInstructorId !== null) {
            $originalInstructor = Instructor::find($excludedInstructorId);
            $approvalStatus = [
                'is_decided' => false,
                'status' => 'pending',
                'substitution_id' => null,
                'original_instructor_id' => $excludedInstructorId,
                'original_instructor_name' => $originalInstructor?->name ?? $schedule->instructor?->name,
                'replacement_instructor_id' => null,
                'replacement_instructor_name' => null,
                'decision_by' => null,
                'decision_by_name' => null,
                'decision_at' => null,
                'rejection_reason' => null,
                'notes' => null,
            ];
        }

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
            warnings: [],
            aiRecommendation: null,
            approvalStatus: $approvalStatus,
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

        // If initial room is not valid, search for alternative available rooms
        $alternativeRooms = [];
        $suggestedAlternative = null;
        $requiresRoomChange = false;

        if (! $isValid) {
            $alternativeRooms = $this->findAlternativeRooms($schedule, $studentCount, $scheduleDate);
            if (! empty($alternativeRooms)) {
                $suggestedAlternative = $alternativeRooms[0];
                $requiresRoomChange = true;
                $notes[] = "Disarankan pindah ke ruangan alternatif: {$suggestedAlternative->roomName} (Kapasitas: {$suggestedAlternative->capacity}).";
            } else {
                $notes[] = 'Tidak ada ruangan alternatif yang kosong dengan kapasitas mencukupi.';
            }
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
            alternativeRooms: $alternativeRooms,
            suggestedAlternativeRoom: $suggestedAlternative,
            requiresRoomChange: $requiresRoomChange,
        );
    }

    /**
     * Search for alternative available rooms with sufficient capacity.
     *
     * @return list<RoomEvaluation>
     */
    public function findAlternativeRooms(Schedule $schedule, int $studentCount, string $scheduleDate): array
    {
        return $this->findAlternativeRoomsForTime(
            excludeRoomId: $schedule->room_id,
            studentCount: $studentCount,
            scheduleDate: $scheduleDate,
            startTime: $schedule->start_time,
            endTime: $schedule->end_time,
            excludeScheduleId: $schedule->id,
        );
    }

    /**
     * Search for alternative available rooms with sufficient capacity for a specific time window.
     *
     * @return list<RoomEvaluation>
     */
    public function findAlternativeRoomsForTime(
        int $excludeRoomId,
        int $studentCount,
        string $scheduleDate,
        string $startTime,
        string $endTime,
        ?int $excludeScheduleId = null,
    ): array {
        $candidateRooms = Room::query()
            ->where('id', '!=', $excludeRoomId)
            ->where('status', 'available')
            ->where('capacity', '>=', $studentCount)
            ->get();

        $alternativeRooms = [];
        $startTime = $this->normalizeTime($startTime);
        $endTime = $this->normalizeTime($endTime);

        foreach ($candidateRooms as $altRoom) {
            $hasConflict = Schedule::query()
                ->where('room_id', $altRoom->id)
                ->when($excludeScheduleId, fn ($query) => $query->where('id', '!=', $excludeScheduleId))
                ->whereDate('date', $scheduleDate)
                ->where('status', '!=', 'cancelled')
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if (! $hasConflict) {
                $alternativeRooms[] = new RoomEvaluation(
                    roomId: $altRoom->id,
                    roomName: $altRoom->name,
                    capacity: $altRoom->capacity,
                    studentCount: $studentCount,
                    isCapacitySufficient: true,
                    isStatusAvailable: true,
                    hasRoomConflict: false,
                    isValid: true,
                    notes: "Ruangan alternatif {$altRoom->name} tersedia (Kapasitas: {$altRoom->capacity}, Siswa: {$studentCount}).",
                );
            }
        }

        // Sort by closest capacity fit (smallest sufficient capacity first)
        usort($alternativeRooms, function (RoomEvaluation $a, RoomEvaluation $b) use ($studentCount) {
            $diffA = $a->capacity - $studentCount;
            $diffB = $b->capacity - $studentCount;
            if ($diffA !== $diffB) {
                return $diffA <=> $diffB;
            }

            return strcmp($a->roomName, $b->roomName);
        });

        return $alternativeRooms;
    }

    /**
     * Resolve cross-schedule conflicts for both candidates and rooms.
     *
     * @param  list<ScheduleEvaluation>  $evaluations
     * @return list<ScheduleEvaluation>
     */
    public function resolveCrossScheduleConflicts(array $evaluations): array
    {
        $evaluations = $this->resolveCrossScheduleCandidateConflicts($evaluations);

        return $this->resolveCrossScheduleRoomConflicts($evaluations);
    }

    /**
     * Detect and resolve potential double-booking when the same candidate is assigned to multiple overlapping affected classes.
     *
     * @param  list<ScheduleEvaluation>  $evaluations
     * @return list<ScheduleEvaluation>
     */
    public function resolveCrossScheduleCandidateConflicts(array $evaluations): array
    {
        /** @var list<array{instructor_id: int, start_time: string, end_time: string, class_name: string}> $allocatedAssignments */
        $allocatedAssignments = [];

        foreach ($evaluations as $eval) {
            // Jika jadwal ini sudah disetujui, instruktur penggantinya sudah pasti teralokasi
            if ($eval->approvalStatus !== null && ($eval->approvalStatus['status'] ?? null) === 'approved' && ! empty($eval->approvalStatus['replacement_instructor_id'])) {
                $allocatedAssignments[] = [
                    'instructor_id' => (int) $eval->approvalStatus['replacement_instructor_id'],
                    'start_time' => $eval->startTime,
                    'end_time' => $eval->endTime,
                    'class_name' => $eval->className,
                ];

                continue;
            }

            if (! $eval->bestCandidate) {
                continue;
            }

            // Check all candidates in validCandidates against current allocatedAssignments
            $stillValidCandidates = [];
            foreach ($eval->validCandidates as $cand) {
                $hasAllocationConflict = false;
                $conflictingClass = null;

                foreach ($allocatedAssignments as $assignment) {
                    if ($assignment['instructor_id'] === $cand->instructorId && $this->intervalsOverlap($assignment['start_time'], $assignment['end_time'], $eval->startTime, $eval->endTime)) {
                        $hasAllocationConflict = true;
                        $conflictingClass = $assignment['class_name'];
                        break;
                    }
                }

                if ($hasAllocationConflict) {
                    $warningMessage = "Kandidat {$cand->instructorName} berpotensi bentrok jika ditugaskan ke dua kelas terdampak sekaligus (bersamaan dengan '{$conflictingClass}').";
                    if (! in_array($warningMessage, $eval->warnings, true)) {
                        $eval->warnings[] = $warningMessage;
                    }

                    $eval->disqualifiedCandidates[] = new CandidateEvaluation(
                        instructorId: $cand->instructorId,
                        instructorName: $cand->instructorName,
                        isValid: false,
                        competencyMatched: true,
                        skillName: $cand->skillName,
                        skillLevel: $cand->skillLevel,
                        hasScheduleConflict: true,
                        hasLeaveConflict: false,
                        score: 0,
                        reasons: ["Bentrok dengan kelas '{$conflictingClass}' yang jadwalnya bersamaan."],
                        disqualificationReason: "Bentrok dengan kelas '{$conflictingClass}' yang jadwalnya bersamaan.",
                        otherClassesCountToday: $cand->otherClassesCountToday,
                    );
                } else {
                    $stillValidCandidates[] = $cand;
                }
            }

            $eval->validCandidates = $stillValidCandidates;

            if (empty($stillValidCandidates)) {
                $eval->bestCandidate = null;
                $eval->status = 'no_candidate';
                $eval->summary = 'Tidak ada kandidat pengganti yang dapat ditugaskan karena kandidat yang ada sudah dialokasikan ke kelas lain yang jadwalnya bersamaan.';
            } else {
                $prevBestId = $eval->bestCandidate->instructorId;
                $eval->bestCandidate = $stillValidCandidates[0];

                if ($eval->bestCandidate->instructorId !== $prevBestId) {
                    $eval->summary .= " (Perhatian: Dialihkan ke {$eval->bestCandidate->instructorName} karena kandidat sebelumnya sudah ditugaskan pada kelas lain yang bersamaan).";
                }

                $allocatedAssignments[] = [
                    'instructor_id' => $eval->bestCandidate->instructorId,
                    'start_time' => $eval->startTime,
                    'end_time' => $eval->endTime,
                    'class_name' => $eval->className,
                ];
            }
        }

        return $evaluations;
    }

    /**
     * Detect and resolve potential room double-booking when multiple affected classes overlap.
     *
     * @param  list<ScheduleEvaluation>  $evaluations
     * @return list<ScheduleEvaluation>
     */
    public function resolveCrossScheduleRoomConflicts(array $evaluations): array
    {
        /** @var list<array{room_id: int, room_name: string, start_time: string, end_time: string, class_name: string}> $allocatedRooms */
        $allocatedRooms = [];

        foreach ($evaluations as $eval) {
            if ($eval->roomEvaluation === null) {
                continue;
            }

            $roomEval = $eval->roomEvaluation;

            // Determine which room this schedule currently intends to use
            $isAlternative = $roomEval->requiresRoomChange && $roomEval->suggestedAlternativeRoom !== null;
            $intendedRoom = $isAlternative ? $roomEval->suggestedAlternativeRoom : $roomEval;

            $overlapsWithAllocated = false;
            $conflictingClass = null;

            foreach ($allocatedRooms as $alloc) {
                if ($alloc['room_id'] === $intendedRoom->roomId && $this->intervalsOverlap($alloc['start_time'], $alloc['end_time'], $eval->startTime, $eval->endTime)) {
                    $overlapsWithAllocated = true;
                    $conflictingClass = $alloc['class_name'];
                    break;
                }
            }

            if ($overlapsWithAllocated) {
                // If alternativeRooms is empty, attempt to populate candidate alternative rooms
                if (empty($roomEval->alternativeRooms)) {
                    $roomEval->alternativeRooms = $this->findAlternativeRoomsForTime(
                        excludeRoomId: $intendedRoom->roomId,
                        studentCount: $roomEval->studentCount,
                        scheduleDate: $eval->date,
                        startTime: $eval->startTime,
                        endTime: $eval->endTime,
                        excludeScheduleId: $eval->scheduleId,
                    );
                }

                // Find next available alternative room that doesn't conflict with allocated rooms
                $nextRoom = null;
                foreach ($roomEval->alternativeRooms as $altRoom) {
                    if ($altRoom->roomId === $intendedRoom->roomId) {
                        continue;
                    }

                    $altOverlaps = false;
                    foreach ($allocatedRooms as $alloc) {
                        if ($alloc['room_id'] === $altRoom->roomId && $this->intervalsOverlap($alloc['start_time'], $alloc['end_time'], $eval->startTime, $eval->endTime)) {
                            $altOverlaps = true;
                            break;
                        }
                    }

                    if (! $altOverlaps) {
                        $nextRoom = $altRoom;
                        break;
                    }
                }

                $warningMessage = "Ruangan {$intendedRoom->roomName} berpotensi bentrok jika digunakan untuk dua kelas terdampak sekaligus (bersamaan dengan '{$conflictingClass}').";
                if (! in_array($warningMessage, $eval->warnings, true)) {
                    $eval->warnings[] = $warningMessage;
                }

                if ($nextRoom) {
                    $roomEval->suggestedAlternativeRoom = $nextRoom;
                    $roomEval->requiresRoomChange = true;
                    $roomEval->notes .= " (Perhatian: Dialihkan ke {$nextRoom->roomName} karena {$intendedRoom->roomName} sudah dialokasikan ke kelas '{$conflictingClass}' pada jam yang sama).";
                    $eval->summary .= " (Ruangan dialihkan ke {$nextRoom->roomName} karena {$intendedRoom->roomName} bersamaan dengan '{$conflictingClass}').";

                    $allocatedRooms[] = [
                        'room_id' => $nextRoom->roomId,
                        'room_name' => $nextRoom->roomName,
                        'start_time' => $eval->startTime,
                        'end_time' => $eval->endTime,
                        'class_name' => $eval->className,
                    ];
                } else {
                    // No other alternative room is available!
                    $roomEval->suggestedAlternativeRoom = null;
                    $roomEval->requiresRoomChange = true;
                    $roomEval->isValid = false;
                    $roomEval->hasRoomConflict = true;

                    if ($eval->hasCandidate()) {
                        $eval->status = 'room_issue';
                    }
                    $eval->summary = "Ruangan {$intendedRoom->roomName} tidak dapat digunakan karena sudah dialokasikan ke kelas '{$conflictingClass}' pada jam yang sama, dan tidak ada ruangan alternatif lain.";
                }
            } else {
                // Record room allocation if room is usable
                if ($roomEval->hasUsableRoom()) {
                    $activeRoom = $isAlternative ? $roomEval->suggestedAlternativeRoom : $roomEval;
                    if ($activeRoom) {
                        $allocatedRooms[] = [
                            'room_id' => $activeRoom->roomId,
                            'room_name' => $activeRoom->roomName,
                            'start_time' => $eval->startTime,
                            'end_time' => $eval->endTime,
                            'class_name' => $eval->className,
                        ];
                    }
                }
            }
        }

        return $evaluations;
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
            return "Berhasil menemukan solusi yang valid (instruktur dan ruangan) untuk seluruh {$totalAffected} kelas yang terdampak.";
        }

        if ($totalResolved > 0) {
            return "Berhasil menyelesaikan {$totalResolved} dari {$totalAffected} kelas. Terdapat kelas yang belum terselesaikan (masalah kandidat atau ruangan).";
        }

        return "Belum ada solusi yang memenuhi syarat untuk {$totalAffected} kelas yang terdampak (kandidat tidak tersedia atau ruangan bermasalah).";
    }
}
