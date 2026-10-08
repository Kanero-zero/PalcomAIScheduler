<?php

namespace Database\Seeders;

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
            ['instructor_id' => 1, 'skill' => 'Microsoft Excel', 'level' => 'advanced'],
            ['instructor_id' => 1, 'skill' => 'Microsoft Word', 'level' => 'advanced'],

            // Kanero
            ['instructor_id' => 2, 'skill' => 'Microsoft Excel', 'level' => 'advanced'],
            ['instructor_id' => 2, 'skill' => 'Microsoft Word', 'level' => 'advanced'],
            ['instructor_id' => 2, 'skill' => 'Microsoft PowerPoint', 'level' => 'intermediate'],

            // Rizky Pratama
            ['instructor_id' => 3, 'skill' => 'Web Programming', 'level' => 'advanced'],
            ['instructor_id' => 3, 'skill' => 'Database MySQL', 'level' => 'advanced'],

            // Dina Oktavia
            ['instructor_id' => 4, 'skill' => 'Desain Grafis', 'level' => 'advanced'],
            ['instructor_id' => 4, 'skill' => 'Adobe Photoshop', 'level' => 'advanced'],

            // Budi Santoso
            ['instructor_id' => 5, 'skill' => 'Microsoft Excel', 'level' => 'intermediate'],
            ['instructor_id' => 5, 'skill' => 'Microsoft PowerPoint', 'level' => 'advanced'],
        ];

        foreach ($skills as $skill) {
            InstructorSkill::updateOrCreate(
                ['instructor_id' => $skill['instructor_id'], 'skill' => $skill['skill']],
                $skill
            );
        }
    }
}
