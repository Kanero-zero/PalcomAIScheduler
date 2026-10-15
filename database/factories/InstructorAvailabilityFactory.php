<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\InstructorAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorAvailability>
 */
class InstructorAvailabilityFactory extends Factory
{
    protected $model = InstructorAvailability::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instructor_id' => Instructor::factory(),
            'day' => fake()->randomElement(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']),
            'start_time' => '08:00',
            'end_time' => '12:00',
        ];
    }
}
