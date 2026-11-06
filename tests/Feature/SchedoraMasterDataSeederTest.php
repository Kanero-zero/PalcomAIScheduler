<?php

use App\Models\Competency;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorSkill;
use App\Models\Room;
use App\Models\User;
use App\Services\Scheduling\CatalogContextResolver;
use Database\Seeders\DatabaseSeeder;

test('database seeder creates complete idempotent scheduling master data', function () {
    $this->seed(DatabaseSeeder::class);

    $word = Competency::query()->where('name', 'Microsoft Word')->first();
    $excel = Competency::query()->where('name', 'Microsoft Excel')->first();
    expect($word)->not->toBeNull()
        ->and($excel)->not->toBeNull();

    foreach (['Wahyu', 'Kanero', 'Rizky Pratama', 'Dina Oktavia', 'Budi Santoso'] as $name) {
        expect(Instructor::query()->where('name', $name)->value('department'))->toBe('computer');
    }

    foreach (['Lab 1', 'Lab 2', 'Lab 3'] as $name) {
        expect(Room::query()->where('name', $name)->value('department'))->toBe('computer');
    }
    expect(Room::query()->where('name', 'Ruang Teori 1')->value('department'))->toBeNull();

    $wahyu = Instructor::query()->where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::query()->where('name', 'Kanero')->firstOrFail();
    expect(InstructorSkill::query()
        ->where('instructor_id', $wahyu->id)
        ->where('skill', 'Microsoft Word')
        ->value('competency_id'))->toBe($word->id)
        ->and(InstructorSkill::query()
            ->where('instructor_id', $kanero->id)
            ->where('skill', 'Microsoft Excel')
            ->value('competency_id'))->toBe($excel->id);

    foreach ([$wahyu, $kanero] as $instructor) {
        foreach (['Senin', 'Rabu'] as $day) {
            $this->assertDatabaseHas('instructor_availabilities', [
                'instructor_id' => $instructor->id,
                'day' => $day,
                'start_time' => '16:00:00',
                'end_time' => '18:00:00',
            ]);
        }
    }

    $skillCount = InstructorSkill::count();
    $availabilityCount = InstructorAvailability::count();
    $this->seed(DatabaseSeeder::class);

    expect(InstructorSkill::count())->toBe($skillCount)
        ->and(InstructorAvailability::count())->toBe($availabilityCount)
        ->and(InstructorAvailability::query()
            ->where('instructor_id', $wahyu->id)
            ->where('day', 'Senin')
            ->where('start_time', '16:00:00')
            ->count())->toBe(1)
        ->and(InstructorSkill::query()
            ->where('instructor_id', $kanero->id)
            ->where('skill', 'Microsoft Excel')
            ->count())->toBe(1);
});

test('seeded scheduling catalog exposes qualified instructors and computer rooms', function () {
    $this->seed(DatabaseSeeder::class);

    $word = Competency::query()->where('name', 'Microsoft Word')->firstOrFail();
    $resolver = app(CatalogContextResolver::class);
    $instructors = collect($resolver->instructorsForCompetency($word->id))->keyBy('name');

    expect($instructors->keys()->all())->toContain('Wahyu', 'Kanero')
        ->and($instructors['Wahyu']['availability'])->toContain(
            ['day' => 'Senin', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
            ['day' => 'Rabu', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
        )->and($instructors['Kanero']['availability'])->toContain(
            ['day' => 'Senin', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
            ['day' => 'Rabu', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
        );

    $roomNames = collect($resolver->availableRooms('computer', 12))->pluck('name')->all();
    expect($roomNames)->toContain('Lab 1', 'Lab 2', 'Lab 3')
        ->not->toContain('Ruang Teori 1');
});

test('database seeder production guard does not create default demo accounts', function () {
    $originalEnvironment = app()->environment();
    app()['env'] = 'production';

    try {
        app(DatabaseSeeder::class)->run();

        expect(User::query()->whereIn('email', [
            'admin@palcomtech.ac.id',
            'admin@example.com',
        ])->exists())->toBeFalse();
    } finally {
        app()['env'] = $originalEnvironment;
    }
});
