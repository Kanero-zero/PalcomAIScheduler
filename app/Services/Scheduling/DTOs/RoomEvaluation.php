<?php

namespace App\Services\Scheduling\DTOs;

/**
 * Data Transfer Object representing the evaluation result of a room for a schedule.
 */
class RoomEvaluation
{
    public function __construct(
        public int $roomId,
        public string $roomName,
        public int $capacity,
        public int $studentCount,
        public bool $isCapacitySufficient,
        public bool $isStatusAvailable,
        public bool $hasRoomConflict,
        public bool $isValid,
        public string $notes,
    ) {}

    /**
     * Convert the room evaluation result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'room_name' => $this->roomName,
            'capacity' => $this->capacity,
            'student_count' => $this->studentCount,
            'is_capacity_sufficient' => $this->isCapacitySufficient,
            'is_status_available' => $this->isStatusAvailable,
            'has_room_conflict' => $this->hasRoomConflict,
            'is_valid' => $this->isValid,
            'notes' => $this->notes,
        ];
    }
}
