<?php

namespace App\Services\Scheduling\DTOs;

/**
 * Data Transfer Object representing the complete scheduling result for an instructor's leave.
 */
class SchedulingResult
{
    /**
     * @param  list<ScheduleEvaluation>  $affectedSchedules
     */
    public function __construct(
        public ?int $leaveId,
        public int $instructorId,
        public string $instructorName,
        public string $leaveDate,
        public string $leaveStartTime,
        public string $leaveEndTime,
        public ?string $leaveReason,
        public array $affectedSchedules,
        public int $totalAffectedSchedules,
        public int $totalResolvedSchedules,
        public bool $allSchedulesResolved,
        public string $summary,
        public ?array $aiSummary = null,
    ) {}

    /**
     * Determine if there are any schedules without available replacements.
     */
    public function hasUnresolvedSchedules(): bool
    {
        return ! $this->allSchedulesResolved;
    }

    /**
     * Convert the scheduling result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'leave_id' => $this->leaveId,
            'instructor_id' => $this->instructorId,
            'instructor_name' => $this->instructorName,
            'leave_date' => $this->leaveDate,
            'leave_start_time' => $this->leaveStartTime,
            'leave_end_time' => $this->leaveEndTime,
            'leave_reason' => $this->leaveReason,
            'total_affected_schedules' => $this->totalAffectedSchedules,
            'total_resolved_schedules' => $this->totalResolvedSchedules,
            'all_schedules_resolved' => $this->allSchedulesResolved,
            'summary' => $this->summary,
            'ai_summary' => $this->aiSummary,
            'affected_schedules' => array_map(fn (ScheduleEvaluation $s) => $s->toArray(), $this->affectedSchedules),
        ];
    }
}
