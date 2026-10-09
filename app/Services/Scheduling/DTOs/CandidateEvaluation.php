<?php

namespace App\Services\Scheduling\DTOs;

/**
 * Data Transfer Object representing the evaluation result of a substitute instructor candidate.
 */
class CandidateEvaluation
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $instructorId,
        public string $instructorName,
        public bool $isValid,
        public bool $competencyMatched,
        public ?string $skillName = null,
        public ?string $skillLevel = null,
        public bool $hasScheduleConflict = false,
        public bool $hasLeaveConflict = false,
        public int $score = 0,
        public array $reasons = [],
        public ?string $disqualificationReason = null,
        public int $otherClassesCountToday = 0,
    ) {}

    /**
     * Convert the evaluation result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'instructor_id' => $this->instructorId,
            'instructor_name' => $this->instructorName,
            'is_valid' => $this->isValid,
            'competency_matched' => $this->competencyMatched,
            'skill_name' => $this->skillName,
            'skill_level' => $this->skillLevel,
            'has_schedule_conflict' => $this->hasScheduleConflict,
            'has_leave_conflict' => $this->hasLeaveConflict,
            'score' => $this->score,
            'reasons' => $this->reasons,
            'disqualification_reason' => $this->disqualificationReason,
            'other_classes_count_today' => $this->otherClassesCountToday,
        ];
    }
}
