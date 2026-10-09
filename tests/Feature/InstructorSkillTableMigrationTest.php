<?php

use App\Models\InstructorSkill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('renaming legacy instructor skills preserves records and model relationships', function () {
    $skill = InstructorSkill::factory()->create();
    $attributes = $skill->getAttributes();
    Schema::rename('instructor_skills', 'instructors_skills');
    $migration = require database_path('migrations/2026_10_09_000000_rename_instructors_skills_to_instructor_skills.php');

    $migration->up();

    expect(Schema::hasTable('instructors_skills'))->toBeFalse();
    $this->assertDatabaseHas('instructor_skills', $attributes);
    expect($skill->fresh()->instructor->id)->toBe($skill->instructor_id);
});

test('rolling back the rename preserves instructor skill records', function () {
    $skill = InstructorSkill::factory()->create();
    $migration = require database_path('migrations/2026_10_09_000000_rename_instructors_skills_to_instructor_skills.php');

    $migration->down();

    expect(Schema::hasTable('instructor_skills'))->toBeFalse();
    $this->assertDatabaseHas('instructors_skills', $skill->getAttributes());
});

test('repeating the rename or rollback preserves existing records', function (string $direction, string $table) {
    $skill = InstructorSkill::factory()->create();
    $migration = require database_path('migrations/2026_10_09_000000_rename_instructors_skills_to_instructor_skills.php');
    $migration->{$direction}();

    $migration->{$direction}();

    $this->assertDatabaseHas($table, $skill->getAttributes());
})->with([
    'already renamed' => ['up', 'instructor_skills'],
    'already rolled back' => ['down', 'instructors_skills'],
]);

test('rename and rollback leave both tables intact when both names exist', function (string $direction) {
    $skill = InstructorSkill::factory()->create();
    $legacyMigration = require database_path('migrations/2026_10_08_044838_create_instructor_skills_table.php');
    $legacyMigration->up();
    $legacyAttributes = array_replace($skill->getAttributes(), ['skill' => 'Legacy skill']);
    DB::table('instructors_skills')->insert($legacyAttributes);
    $migration = require database_path('migrations/2026_10_09_000000_rename_instructors_skills_to_instructor_skills.php');

    $migration->{$direction}();

    $this->assertDatabaseHas('instructor_skills', $skill->getAttributes());
    $this->assertDatabaseHas('instructors_skills', $legacyAttributes);
})->with(['up', 'down']);

test('rename and rollback do nothing when neither table exists', function (string $direction) {
    Schema::rename('instructor_skills', 'parked_instructor_skills');
    $migration = require database_path('migrations/2026_10_09_000000_rename_instructors_skills_to_instructor_skills.php');

    $migration->{$direction}();

    expect(Schema::hasTable('instructor_skills'))->toBeFalse();
    expect(Schema::hasTable('instructors_skills'))->toBeFalse();
    expect(Schema::hasTable('parked_instructor_skills'))->toBeTrue();
})->with(['up', 'down']);
