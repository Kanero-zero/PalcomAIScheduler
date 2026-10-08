<?php

namespace Database\Seeders;

use App\Models\CourseClass;
use Illuminate\Database\Seeder;

class CourseClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            ['id' => 1, 'name' => 'Kelas Microsoft Excel - Reguler Siang', 'subject' => 'Microsoft Excel', 'student_count' => 10],
            ['id' => 2, 'name' => 'Kelas Microsoft Word - Reguler Sore', 'subject' => 'Microsoft Word', 'student_count' => 12],
            ['id' => 3, 'name' => 'Kelas Desain Grafis - Reguler Pagi', 'subject' => 'Desain Grafis', 'student_count' => 15],
            ['id' => 4, 'name' => 'Kelas Web Programming - Reguler Pagi', 'subject' => 'Web Programming', 'student_count' => 14],
        ];

        foreach ($classes as $class) {
            CourseClass::updateOrCreate(['id' => $class['id']], $class);
        }
    }
}
