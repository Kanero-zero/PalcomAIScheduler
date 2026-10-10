<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleSubstitution>
 */
class ScheduleSubstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instructor_leave_id' => InstructorLeave::factory(),
            'schedule_id' => Schedule::factory(),
            'original_instructor_id' => Instructor::factory(),
            'replacement_instructor_id' => null,
            'status' => 'pending',
            'decision_by' => null,
            'decision_at' => null,
            'rejection_reason' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the substitution is approved.
     */
    public function approved(?int $replacementInstructorId = null, ?int $decisionBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'replacement_instructor_id' => $replacementInstructorId ?? Instructor::factory(),
            'status' => 'approved',
            'decision_by' => $decisionBy ?? User::factory()->admin(),
            'decision_at' => now(),
            'notes' => 'Disetujui untuk mengajar.',
        ]);
    }

    /**
     * Indicate that the substitution is rejected.
     */
    public function rejected(string $reason = 'Kandidat memiliki agenda rapat mendadak.', ?int $decisionBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'replacement_instructor_id' => null,
            'status' => 'rejected',
            'decision_by' => $decisionBy ?? User::factory()->admin(),
            'decision_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
