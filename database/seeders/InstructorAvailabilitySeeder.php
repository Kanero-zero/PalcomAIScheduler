<?php

namespace Database\Seeders;

use App\Models\Instructor;
use App\Models\InstructorAvailability;
use Illuminate\Database\Seeder;

class InstructorAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $availabilities = [
            ['instructor' => 'Wahyu', 'day' => 'Senin', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
            ['instructor' => 'Wahyu', 'day' => 'Rabu', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
            ['instructor' => 'Kanero', 'day' => 'Senin', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
            ['instructor' => 'Kanero', 'day' => 'Rabu', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
        ];

        foreach ($availabilities as $availability) {
            $instructor = Instructor::query()->where('name', $availability['instructor'])->firstOrFail();

            InstructorAvailability::updateOrCreate(
                [
                    'instructor_id' => $instructor->id,
                    'day' => $availability['day'],
                    'start_time' => $availability['start_time'],
                ],
                ['end_time' => $availability['end_time']],
            );
        }
    }
}
