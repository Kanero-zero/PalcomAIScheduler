<?php

use App\Models\Competency;
use App\Models\CourseClass;
use App\Models\CurriculumVersion;
use App\Models\Facility;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorLeave;
use App\Models\InstructorSkill;
use App\Models\Program;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\Scheduling\MasterDataReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('g3 master references expose active programs and published versions only for requested department', function () {
    $computer = Program::create(['code' => 'G3-COMP', 'name' => 'Komputer', 'department' => 'computer', 'is_active' => true]);
    $inactive = Program::create(['code' => 'G3-OFF', 'name' => 'Nonaktif', 'department' => 'computer', 'is_active' => false]);
    $english = Program::create(['code' => 'G3-ENG', 'name' => 'SpeakOut', 'department' => 'speakout', 'is_active' => true]);

    $published = CurriculumVersion::create(['program_id' => $computer->id, 'version_code' => 'G3.1', 'status' => 'published']);
    CurriculumVersion::create(['program_id' => $computer->id, 'version_code' => 'G3.2', 'status' => 'draft']);
    CurriculumVersion::create(['program_id' => $inactive->id, 'version_code' => 'G3.3', 'status' => 'published']);
    CurriculumVersion::create(['program_id' => $english->id, 'version_code' => 'G3.4', 'status' => 'published']);

    $result = app(MasterDataReferenceService::class)->forDepartment('computer');

    expect($result['programs']->pluck('id')->all())->toBe([$computer->id])
        ->and($result['curriculum_versions']->pluck('id')->all())->toBe([$published->id]);
});

test('g3 master references fail closed for room capacity, maintenance and mandatory facilities', function () {
    $available = Room::factory()->create(['department' => 'computer', 'capacity' => 20, 'status' => 'available']);
    $small = Room::factory()->create(['department' => 'computer', 'capacity' => 8, 'status' => 'available']);
    $maintenance = Room::factory()->create(['department' => 'computer', 'capacity' => 30, 'status' => 'maintenance']);
    Room::factory()->create(['department' => 'speakout', 'capacity' => 30, 'status' => 'available']);
    $withoutFacility = Room::factory()->create(['department' => 'computer', 'capacity' => 30, 'status' => 'available']);

    $lan = Facility::create(['code' => 'G3-LAN', 'name' => 'LAN', 'category' => 'network', 'is_active' => true]);
    $available->facilities()->attach($lan->id, ['quantity' => 1, 'condition' => 'good']);
    $small->facilities()->attach($lan->id, ['quantity' => 1, 'condition' => 'good']);
    $maintenance->facilities()->attach($lan->id, ['quantity' => 1, 'condition' => 'good']);

    $service = app(MasterDataReferenceService::class);
    $ids = $service->forDepartment('computer', 15, ['G3-LAN'])['rooms']->pluck('id')->all();
    expect($ids)->toBe([$available->id])
        ->and($ids)->not->toContain($withoutFacility->id)
        ->and($service->forDepartment('computer', 15, ['UNKNOWN'])['rooms'])->toHaveCount(0);
});

test('g3 master references require an active competency for eligible instructors', function () {
    $skill = Competency::create(['name' => 'G3 Web', 'department' => 'computer', 'is_active' => true]);
    $disabledSkill = Competency::create(['name' => 'G3 Disabled', 'department' => 'computer', 'is_active' => false]);
    $eligible = Instructor::factory()->create(['department' => 'computer', 'status' => 'active']);
    $ineligible = Instructor::factory()->create(['department' => 'computer', 'status' => 'active']);
    $inactive = Instructor::factory()->create(['department' => 'computer', 'status' => 'inactive']);
    InstructorSkill::create(['instructor_id' => $eligible->id, 'competency_id' => $skill->id, 'skill' => 'G3 Web', 'level' => 'advanced']);
    InstructorSkill::create(['instructor_id' => $ineligible->id, 'competency_id' => $disabledSkill->id, 'skill' => 'G3 Disabled', 'level' => 'advanced']);
    InstructorSkill::create(['instructor_id' => $inactive->id, 'competency_id' => $skill->id, 'skill' => 'G3 Web', 'level' => 'advanced']);
    InstructorAvailability::create(['instructor_id' => $eligible->id, 'day' => 'Senin', 'start_time' => '08:00', 'end_time' => '12:00']);

    $instructors = app(MasterDataReferenceService::class)->forDepartment('computer')['instructors'];
    expect($instructors->pluck('id')->all())->toBe([$eligible->id])
        ->and($instructors->first()->availabilities)->toHaveCount(1);
});

test('g3 master references reject unsupported department and nonpositive capacity', function () {
    $service = app(MasterDataReferenceService::class);
    expect(fn () => $service->forDepartment('unknown'))->toThrow(ValidationException::class)
        ->and(fn () => $service->forDepartment('computer', 0))->toThrow(ValidationException::class);
});

test('g3 blocking state only includes official schedules and un-rejected leaves within range', function () {
    $class = CourseClass::factory()->create();
    $instructor = Instructor::factory()->create();
    $room = Room::factory()->create();

    $official = Schedule::factory()->create([
        'course_class_id' => $class->id,
        'instructor_id' => $instructor->id,
        'room_id' => $room->id,
        'date' => '2026-11-02',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'status' => 'scheduled',
    ]);
    Schedule::factory()->create([
        'course_class_id' => $class->id,
        'instructor_id' => $instructor->id,
        'room_id' => $room->id,
        'date' => '2026-11-02',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'status' => 'cancelled',
    ]);
    Schedule::factory()->create([
        'course_class_id' => $class->id,
        'instructor_id' => $instructor->id,
        'room_id' => $room->id,
        'date' => '2026-12-02',
        'status' => 'scheduled',
    ]);
    $pendingLeave = InstructorLeave::factory()->create(['instructor_id' => $instructor->id, 'date' => '2026-11-02', 'status' => 'pending']);
    InstructorLeave::factory()->create(['instructor_id' => $instructor->id, 'date' => '2026-11-02', 'status' => 'rejected']);

    $result = app(MasterDataReferenceService::class)->blockingState('2026-11-01', '2026-11-30');
    expect($result['schedules']->pluck('id')->all())->toBe([$official->id])
        ->and($result['leaves']->pluck('id')->all())->toBe([$pendingLeave->id]);
});

test('g3 blocking state refuses invalid or reversed date ranges', function () {
    $service = app(MasterDataReferenceService::class);
    expect(fn () => $service->blockingState('2026-13-01', '2026-12-01'))->toThrow(ValidationException::class)
        ->and(fn () => $service->blockingState('2026-12-02', '2026-12-01'))->toThrow(ValidationException::class);
});
