<?php

namespace Database\Seeders;

use App\Models\Competency;
use App\Models\InstructorSkill;
use Illuminate\Database\Seeder;

class CompetencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $competencies = [
            // Bidang Komputer
            ['name' => 'Microsoft Word', 'department' => 'computer', 'is_active' => true],
            ['name' => 'Microsoft Excel', 'department' => 'computer', 'is_active' => true],
            ['name' => 'Microsoft PowerPoint', 'department' => 'computer', 'is_active' => true],
            ['name' => 'Desain Grafis', 'department' => 'computer', 'is_active' => true],
            ['name' => 'Web Programming', 'department' => 'computer', 'is_active' => true],
            ['name' => 'AI & Python', 'department' => 'computer', 'is_active' => true],
            ['name' => 'Database MySQL', 'department' => 'computer', 'is_active' => true],
            ['name' => 'Adobe Photoshop', 'department' => 'computer', 'is_active' => true],

            // Bidang SpeakOut
            ['name' => 'SpeakOut Level 1', 'department' => 'speakout', 'is_active' => true],
            ['name' => 'SpeakOut Level 2', 'department' => 'speakout', 'is_active' => true],
            ['name' => 'SpeakOut Level 3', 'department' => 'speakout', 'is_active' => true],
        ];

        foreach ($competencies as $item) {
            $comp = Competency::updateOrCreate(
                ['name' => $item['name']],
                $item
            );

            // Backfill matching instructor_skills without losing any records
            InstructorSkill::whereRaw('LOWER(TRIM(skill)) = ?', [strtolower(trim($item['name']))])
                ->whereNull('competency_id')
                ->update(['competency_id' => $comp->id]);
        }
    }
}
