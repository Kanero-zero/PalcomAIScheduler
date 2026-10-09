<?php

namespace App\Services\Scheduling\DTOs;

/**
 * Data Transfer Object representing the evaluation result of a room for a schedule.
 */
class RoomEvaluation
{
    /**
     * @param  list<RoomEvaluation>  $alternativeRooms
     */
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
        public array $alternativeRooms = [],
        public ?RoomEvaluation $suggestedAlternativeRoom = null,
        public bool $requiresRoomChange = false,
    ) {}

    /**
     * Determine if a workable room is available (either original or alternative).
     */
    public function hasUsableRoom(): bool
    {
        if ($this->requiresRoomChange) {
            return $this->suggestedAlternativeRoom !== null && $this->suggestedAlternativeRoom->isValid;
        }

        return $this->isValid;
    }

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
            'requires_room_change' => $this->requiresRoomChange,
            'has_usable_room' => $this->hasUsableRoom(),
            'suggested_alternative_room' => $this->suggestedAlternativeRoom?->toArray(),
            'alternative_rooms' => array_map(fn (RoomEvaluation $r) => $r->toArray(), $this->alternativeRooms),
        ];
    }
}
