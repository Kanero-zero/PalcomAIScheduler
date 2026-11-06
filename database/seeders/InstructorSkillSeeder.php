<?php

namespace Database\Seeders;

use App\Models\Competency;
use App\Models\Instructor;
use App\Models\InstructorSkill;
use Illuminate\Database\Seeder;

class InstructorSkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            // Wahyu
            ['instructor' => 'Wahyu', 'skill' => 'Microsoft Excel', 'level' => 'advanced'],
            ['instructor' => 'Wahyu', 'skill' => 'Microsoft Word', 'level' => 'advanced'],

            // Kanero
            ['instructor' => 'Kanero', 'skill' => 'Microsoft Excel', 'level' => 'advanced'],
            ['instructor' => 'Kanero', 'skill' => 'Microsoft Word', 'level' => 'advanced'],
            ['instructor' => 'Kanero', 'skill' => 'Microsoft PowerPoint', 'level' => 'intermediate'],

            // Rizky Pratama
            ['instructor' => 'Rizky Pratama', 'skill' => 'Web Programming', 'level' => 'advanced'],
            ['instructor' => 'Rizky Pratama', 'skill' => 'Database MySQL', 'level' => 'advanced'],

            // Dina Oktavia
            ['instructor' => 'Dina Oktavia', 'skill' => 'Desain Grafis', 'level' => 'advanced'],
            ['instructor' => 'Dina Oktavia', 'skill' => 'Adobe Photoshop', 'level' => 'advanced'],

            // Budi Santoso
            ['instructor' => 'Budi Santoso', 'skill' => 'Microsoft Excel', 'level' => 'intermediate'],
            ['instructor' => 'Budi Santoso', 'skill' => 'Microsoft PowerPoint', 'level' => 'advanced'],
        ];

        foreach ($skills as $skill) {
            $instructor = Instructor::query()->where('name', $skill['instructor'])->firstOrFail();
            $competency = Competency::query()->where('name', $skill['skill'])->firstOrFail();

            InstructorSkill::updateOrCreate(
                ['instructor_id' => $instructor->id, 'skill' => $skill['skill']],
                ['competency_id' => $competency->id, 'level' => $skill['level']],
            );
        }
    }
}
