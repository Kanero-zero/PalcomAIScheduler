<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['leave', 'engine', 'ai', 'approval', 'schedule']),
            'state' => fake()->randomElement(['success', 'fallback', 'not_applicable', 'pending', 'rejected']),
            'actor' => fake()->name(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'subject' => 'Pengajuan Izin #'.fake()->numberBetween(1, 100),
            'instructor_leave_id' => null,
            'schedule_id' => null,
            'fingerprint' => null,
            'metadata' => [],
        ];
    }
}
