<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\InstructorLeave;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorLeave>
 */
class InstructorLeaveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instructor_id' => Instructor::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'start_time' => '13:00',
            'end_time' => '18:00',
            'reason' => fake()->sentence(),
            'status' => 'approved',
        ];
    }
}
