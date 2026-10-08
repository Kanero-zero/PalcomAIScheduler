<?php

namespace Database\Factories;

use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_class_id' => CourseClass::factory(),
            'instructor_id' => Instructor::factory(),
            'room_id' => Room::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'status' => 'scheduled',
        ];
    }
}
