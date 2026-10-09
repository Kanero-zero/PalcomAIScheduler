<?php

namespace App\Services\Scheduling\DTOs;

/**
 * Data Transfer Object representing the evaluation result for a single affected schedule.
 */
class ScheduleEvaluation
{
    /**
     * @param  list<CandidateEvaluation>  $candidates
     * @param  list<CandidateEvaluation>  $validCandidates
     * @param  list<CandidateEvaluation>  $disqualifiedCandidates
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $scheduleId,
        public int $courseClassId,
        public string $className,
        public string $subject,
        public string $date,
        public string $startTime,
        public string $endTime,
        public ?RoomEvaluation $roomEvaluation,
        public string $status,
        public array $candidates,
        public array $validCandidates,
        public array $disqualifiedCandidates,
        public ?CandidateEvaluation $bestCandidate,
        public string $summary,
        public array $warnings = [],
    ) {}

    /**
     * Determine if a valid replacement instructor was found.
     */
    public function hasCandidate(): bool
    {
        return ! empty($this->validCandidates);
    }

    /**
     * Determine if a workable room is available (original or alternative).
     */
    public function hasValidRoom(): bool
    {
        return $this->roomEvaluation === null || $this->roomEvaluation->hasUsableRoom();
    }

    /**
     * Determine if the schedule is fully resolved (both instructor and room are valid).
     */
    public function isResolved(): bool
    {
        return $this->hasCandidate() && $this->hasValidRoom();
    }

    /**
     * Convert the evaluation result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'course_class_id' => $this->courseClassId,
            'class_name' => $this->className,
            'subject' => $this->subject,
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'room' => $this->roomEvaluation?->toArray(),
            'status' => $this->status,
            'is_resolved' => $this->isResolved(),
            'has_candidate' => $this->hasCandidate(),
            'has_valid_room' => $this->hasValidRoom(),
            'best_candidate' => $this->bestCandidate?->toArray(),
            'valid_candidates' => array_map(fn (CandidateEvaluation $c) => $c->toArray(), $this->validCandidates),
            'disqualified_candidates' => array_map(fn (CandidateEvaluation $c) => $c->toArray(), $this->disqualifiedCandidates),
            'summary' => $this->summary,
            'warnings' => $this->warnings,
        ];
    }
}
