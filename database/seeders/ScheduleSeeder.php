<?php

namespace Database\Seeders;

use App\Models\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tanggal target: Kamis terdekat
        $demoDate = Carbon::now()->next(Carbon::THURSDAY)->toDateString();

        $schedules = [
            // Wahyu: 2 Kelas di Kamis siang-sore (kelas yang terdampak saat Wahyu izin)
            [
                'id' => 1,
                'course_class_id' => 1, // Microsoft Excel
                'instructor_id' => 1,   // Wahyu
                'room_id' => 1,         // Lab 1
                'date' => $demoDate,
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'status' => 'scheduled',
            ],
            [
                'id' => 2,
                'course_class_id' => 2, // Microsoft Word
                'instructor_id' => 1,   // Wahyu
                'room_id' => 1,         // Lab 1
                'date' => $demoDate,
                'start_time' => '16:00:00',
                'end_time' => '18:00:00',
                'status' => 'scheduled',
            ],

            // Kanero: Kelas pagi 09.00-11.00 (sehingga Kanero KOSONG di 13.00-18.00)
            [
                'id' => 3,
                'course_class_id' => 1, // Microsoft Excel
                'instructor_id' => 2,   // Kanero
                'room_id' => 2,         // Lab 2
                'date' => $demoDate,
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'status' => 'scheduled',
            ],

            // Dina Oktavia: Kelas Desain Grafis pagi di Lab 1
            [
                'id' => 4,
                'course_class_id' => 3, // Desain Grafis
                'instructor_id' => 4,   // Dina Oktavia
                'room_id' => 1,         // Lab 1
                'date' => $demoDate,
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'status' => 'scheduled',
            ],

            // Rizky Pratama: Kelas Web Programming siang di Ruang Teori 1
            [
                'id' => 5,
                'course_class_id' => 4, // Web Programming
                'instructor_id' => 3,   // Rizky Pratama
                'room_id' => 4,         // Ruang Teori 1
                'date' => $demoDate,
                'start_time' => '13:00:00',
                'end_time' => '16:00:00',
                'status' => 'scheduled',
            ],
        ];

        foreach ($schedules as $schedule) {
            Schedule::updateOrCreate(['id' => $schedule['id']], $schedule);
        }
    }
}
