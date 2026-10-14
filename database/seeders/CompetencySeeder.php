<?php

namespace Database\Seeders;

use App\Models\Competency;
use App\Models\InstructorSkill;
use Illuminate\Database\Seeder;

/**
 * Seeder data awal master kompetensi.
 *
 * Menginisialisasi daftar materi kompetensi awal untuk kebutuhan fungsionalitas sistem
 * dan pengujian, diselaraskan dengan materi yang sudah tersimpan pada basis data.
 * Catatan: Daftar ini merupakan dataset awal pengembangan, bukan penetapan kurikulum/katalog
 * resmi definitif institusi (yang memerlukan persetujuan Project Leader / manajemen akademik).
 */
class CompetencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $competencies = [
            // Materi Komputer (diselaraskan dengan materi pada data awal)
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
