<?php

namespace Database\Seeders;

use App\Models\InstructorLeave;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InstructorLeaveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demoDate = Carbon::now()->next(Carbon::THURSDAY)->toDateString();

        $leaves = [
            [
                'id' => 1,
                'instructor_id' => 1, // Wahyu
                'date' => $demoDate,
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
                'reason' => 'Izin Urusan Keluarga Mendadak',
                'status' => 'approved',
            ],
        ];

        foreach ($leaves as $leave) {
            InstructorLeave::updateOrCreate(['id' => $leave['id']], $leave);
        }
    }
}
