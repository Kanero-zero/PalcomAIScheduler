<?php

use App\Models\Competency;
use App\Models\CurriculumVersion;
use App\Models\Facility;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\Scheduling\SharedDraftNormalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00', 'Asia/Jakarta')));
afterEach(fn () => Carbon::setTestNow());

function g3DraftInput(array $override = []): array
{
    return array_replace_recursive([
        'input_mode' => 'ai_assistant',
        'title' => 'Kelas Web Programming',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 16,
        'start_date' => '2026-11-23',
        'end_date' => '2027-02-15',
        'total_meetings' => 24,
        'frequency_per_week' => 2,
        'session_duration_minutes' => 120,
        'preferred_weekdays' => ['Rabu', 'Senin'],
        'preferred_time_window' => ['start' => '08:00', 'end' => '12:00'],
    ], $override);
}

test('g3 shared draft normalizer returns deterministic canonical payload for both modes', function () {
    $service = app(SharedDraftNormalizationService::class);
    $ai = $service->normalize(g3DraftInput([
        'requested_by' => 9999,
        'status' => 'approved',
        'selected_option_id' => 42,
    ]));
    $manual = $service->normalize(g3DraftInput(['input_mode' => 'manual_input']));

    expect($ai['input_mode'])->toBe('ai_assistant')
        ->and($manual['input_mode'])->toBe('manual_input')
        ->and($ai['preferred_weekdays'])->toBe(['Senin', 'Rabu'])
        ->and($ai['preferred_time_window'])->toBe(['start' => '08:00:00', 'end' => '12:00:00'])
        ->and($ai)->not->toHaveKeys(['requested_by', 'status', 'selected_option_id'])
        ->and($ai['total_meetings'])->toBe(24)
        ->and(Schedule::count())->toBe(0);
});

test('g3 shared draft requires published curriculum from active same-department program', function () {
    $computer = Program::create(['code' => 'G302-COMP', 'name' => 'Komputer', 'department' => 'computer', 'is_active' => true]);
    $english = Program::create(['code' => 'G302-ENG', 'name' => 'English', 'department' => 'speakout', 'is_active' => true]);
    $published = CurriculumVersion::create(['program_id' => $computer->id, 'version_code' => '2026.1', 'status' => 'published']);
    $draft = CurriculumVersion::create(['program_id' => $computer->id, 'version_code' => '2026.2', 'status' => 'draft']);
    $service = app(SharedDraftNormalizationService::class);

    expect($service->normalize(g3DraftInput([
        'program_id' => $computer->id,
        'curriculum_version_id' => $published->id,
    ]))['curriculum_version_id'])->toBe($published->id)
        ->and(fn () => $service->normalize(g3DraftInput(['program_id' => $computer->id, 'curriculum_version_id' => $draft->id])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['program_id' => $english->id])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['curriculum_version_id' => $published->id])))
        ->toThrow(ValidationException::class);
});

test('g3 shared draft validates active competencies and facilities by master data', function () {
    $activeCompetency = Competency::create(['name' => 'G302 Code', 'department' => 'computer', 'is_active' => true]);
    $englishCompetency = Competency::create(['name' => 'G302 English', 'department' => 'speakout', 'is_active' => true]);
    $facility = Facility::create(['code' => 'G302-LAN', 'name' => 'LAN', 'category' => 'network', 'is_active' => true]);
    Facility::create(['code' => 'G302-INACTIVE', 'name' => 'Old', 'category' => 'network', 'is_active' => false]);
    $service = app(SharedDraftNormalizationService::class);

    $result = $service->normalize(g3DraftInput([
        'required_competency_ids' => [$activeCompetency->id],
        'required_facility_codes' => [$facility->code],
    ]));
    expect($result['required_competency_ids'])->toBe([$activeCompetency->id])
        ->and($result['required_facility_codes'])->toBe([$facility->code])
        ->and(fn () => $service->normalize(g3DraftInput(['required_competency_ids' => [$englishCompetency->id]])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['required_facility_codes' => ['G302-INACTIVE']])))
        ->toThrow(ValidationException::class);
});

test('g3 shared draft validates explicit preferred resources without guessing missing resources', function () {
    $validInstructor = Instructor::factory()->create(['department' => 'computer', 'status' => 'active']);
    $englishInstructor = Instructor::factory()->create(['department' => 'speakout', 'status' => 'active']);
    $validRoom = Room::factory()->create(['department' => 'computer', 'status' => 'available', 'capacity' => 20]);
    $smallRoom = Room::factory()->create(['department' => 'computer', 'status' => 'available', 'capacity' => 10]);
    $service = app(SharedDraftNormalizationService::class);

    expect($service->normalize(g3DraftInput([
        'preferred_instructor_id' => $validInstructor->id,
        'preferred_room_id' => $validRoom->id,
    ]))['preferred_room_id'])->toBe($validRoom->id)
        ->and($service->normalize(g3DraftInput())['preferred_instructor_id'])->toBeNull()
        ->and($service->normalize(g3DraftInput())['preferred_room_id'])->toBeNull()
        ->and(fn () => $service->normalize(g3DraftInput(['preferred_instructor_id' => $englishInstructor->id])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['preferred_room_id' => $smallRoom->id])))
        ->toThrow(ValidationException::class);
});

test('g3 shared draft rejects invalid mode weekday schedule frequency or too short a window', function () {
    $service = app(SharedDraftNormalizationService::class);
    expect(fn () => $service->normalize(g3DraftInput(['input_mode' => 'other'])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['preferred_weekdays' => ['Minggu']])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['preferred_weekdays' => ['Senin', 'Senin']])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['frequency_per_week' => 3])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['preferred_time_window' => ['start' => '11:00', 'end' => '12:00']])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['preferred_time_window' => ['start' => '12:00', 'end' => '10:00']])))
        ->toThrow(ValidationException::class);
});

test('g3 shared draft respects WIB date boundary and never invents a title', function () {
    $service = app(SharedDraftNormalizationService::class);
    expect(fn () => $service->normalize(g3DraftInput(['start_date' => '2026-10-16', 'end_date' => '2026-11-30'])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['start_date' => '2026-11-23', 'end_date' => '2026-11-20'])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['title' => ''])))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->normalize(g3DraftInput(['student_count' => 0])))
        ->toThrow(ValidationException::class);
});
