<?php

namespace Database\Seeders;

use App\Models\Instructor;
use Illuminate\Database\Seeder;

class InstructorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $instructors = [
            ['id' => 1, 'name' => 'Wahyu', 'department' => 'computer', 'status' => 'active'],
            ['id' => 2, 'name' => 'Kanero', 'department' => 'computer', 'status' => 'active'],
            ['id' => 3, 'name' => 'Rizky Pratama', 'department' => 'computer', 'status' => 'active'],
            ['id' => 4, 'name' => 'Dina Oktavia', 'department' => 'computer', 'status' => 'active'],
            ['id' => 5, 'name' => 'Budi Santoso', 'department' => 'computer', 'status' => 'active'],
        ];

        foreach ($instructors as $instructor) {
            Instructor::updateOrCreate(['id' => $instructor['id']], $instructor);
        }
    }
}
