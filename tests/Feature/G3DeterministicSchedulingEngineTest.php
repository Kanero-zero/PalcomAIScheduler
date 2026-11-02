<?php

use App\Models\Competency;
use App\Models\CourseClass;
use App\Models\CurriculumModule;
use App\Models\CurriculumPeriod;
use App\Models\CurriculumSubject;
use App\Models\CurriculumVersion;
use App\Models\Facility;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorLeave;
use App\Models\InstructorSkill;
use App\Models\Program;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\Scheduling\DeterministicSchedulingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00', 'Asia/Jakarta')));
afterEach(fn () => Carbon::setTestNow());

function g303Draft(array $overrides = []): array
{
    return array_replace([
        'input_mode' => 'ai_assistant',
        'title' => 'G303 Kelas Uji',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 12,
        'start_date' => '2026-11-23',
        'end_date' => '2027-02-28',
        'total_meetings' => 24,
        'frequency_per_week' => 2,
        'session_duration_minutes' => 120,
        'preferred_weekdays' => ['Senin', 'Rabu'],
        'preferred_time_window' => ['start' => '08:00', 'end' => '12:00'],
        'required_competency_ids' => [],
    ], $overrides);
}

function g303Fixture(string $department = 'computer', int $instructors = 1, int $rooms = 1): array
{
    $competency = Competency::create(['name' => 'G303 Test '.$department, 'department' => $department, 'is_active' => true]);
    $teachers = [];
    for ($i = 0; $i < $instructors; $i++) {
        $teacher = Instructor::factory()->create(['department' => $department, 'status' => 'active']);
        InstructorSkill::create(['instructor_id' => $teacher->id, 'competency_id' => $competency->id, 'skill' => $competency->name, 'level' => 'advanced']);
        foreach (['Senin', 'Rabu', 'Jumat'] as $day) {
            InstructorAvailability::create(['instructor_id' => $teacher->id, 'day' => $day, 'start_time' => '08:00', 'end_time' => '12:00']);
        }
        $teachers[] = $teacher;
    }
    $labs = [];
    for ($i = 0; $i < $rooms; $i++) {
        $labs[] = Room::factory()->create(['department' => $department, 'capacity' => 30, 'status' => 'available']);
    }

    return ['competency' => $competency, 'instructors' => $teachers, 'rooms' => $labs];
}

test('g303 generates complete 24 meeting plan from database with stable deterministic output', function () {
    $data = g303Fixture('computer', 2, 2);
    $draft = g303Draft(['required_competency_ids' => [$data['competency']->id]]);
    $engine = app(DeterministicSchedulingEngine::class);
    $first = $engine->generate($draft);
    $second = $engine->generate($draft);

    expect($first)->toBe($second)
        ->and($first['alternatives_count'])->toBe(3)
        ->and($first['search_exhausted'])->toBeFalse()
        ->and($first['options'][0]['option_label'])->toBe('A')
        ->and($first['options'][1]['option_label'])->toBe('B')
        ->and($first['options'][2]['option_label'])->toBe('C')
        ->and(Schedule::count())->toBe(0);

    $signatures = [];
    foreach ($first['options'] as $option) {
        $meetings = $option['proposed_schedules_payload'];
        expect($meetings)->toHaveCount(24)
            ->and($option['conflicts_count'])->toBe(0)
            ->and($option['score'])->toBeGreaterThanOrEqual(0)
            ->and($option['score'])->toBeLessThanOrEqual(100)
            ->and(array_column($meetings, 'meeting_sequence'))->toBe(range(1, 24))
            ->and(count(array_unique(array_column($meetings, 'date'))))->toBe(24)
            ->and($meetings[0]['date'])->toBe('2026-11-23');
        $signatures[] = json_encode($meetings);
    }
    expect(count(array_unique($signatures)))->toBe(3);
});

test('g303 rejects a conflict on the last meeting rather than returning partial or unsafe plan', function () {
    $data = g303Fixture();
    $draft = g303Draft([
        'total_meetings' => 4,
        'end_date' => '2026-12-04',
        'preferred_time_window' => ['start' => '08:00', 'end' => '10:00'],
        'required_competency_ids' => [$data['competency']->id],
    ]);
    // Four dates: Nov 23, Nov 25, Nov 30 and Dec 2.
    Schedule::factory()->create([
        'course_class_id' => CourseClass::factory()->create()->id,
        'room_id' => $data['rooms'][0]->id,
        'instructor_id' => $data['instructors'][0]->id,
        'date' => '2026-12-02',
        'start_time' => '08:30:00',
        'end_time' => '09:30:00',
        'status' => 'rescheduled',
    ]);
    $result = app(DeterministicSchedulingEngine::class)->generate($draft);
    expect($result['alternatives_count'])->toBe(0)
        ->and($result['options'])->toBe([])
        ->and($result['reasons'])->not->toBeEmpty();
});

test('g303 treats adjacent schedule as available and ignores nonofficial pending schedule', function () {
    $data = g303Fixture();
    $draft = g303Draft([
        'total_meetings' => 1, 'end_date' => '2026-11-25',
        'preferred_time_window' => ['start' => '08:00', 'end' => '10:00'],
        'required_competency_ids' => [$data['competency']->id],
    ]);
    $class = CourseClass::factory()->create();
    foreach ([['06:00:00', '08:00:00', 'scheduled'], ['08:00:00', '10:00:00', 'pending']] as [$start, $end, $status]) {
        Schedule::factory()->create([
            'course_class_id' => $class->id,
            'room_id' => $data['rooms'][0]->id,
            'instructor_id' => $data['instructors'][0]->id,
            'date' => '2026-11-23', 'start_time' => $start,
            'end_time' => $end, 'status' => $status,
        ]);
    }
    $result = app(DeterministicSchedulingEngine::class)->generate($draft);
    expect($result['alternatives_count'])->toBeGreaterThan(0)
        ->and($result['options'][0]['proposed_schedules_payload'][0]['start_time'])->toBe('08:00:00');
});

test('g303 fails closed for missing availability and leaves even when resources exist', function () {
    $data = g303Fixture();
    $teacher = $data['instructors'][0];
    $draft = g303Draft([
        'total_meetings' => 1, 'end_date' => '2026-11-25',
        'preferred_time_window' => ['start' => '08:00', 'end' => '10:00'],
        'required_competency_ids' => [$data['competency']->id],
    ]);
    $teacher->availabilities()->delete();
    expect(app(DeterministicSchedulingEngine::class)->generate($draft)['alternatives_count'])->toBe(0);
    InstructorAvailability::create(['instructor_id' => $teacher->id, 'day' => 'Senin', 'start_time' => '08:00', 'end_time' => '12:00']);
    InstructorLeave::create(['instructor_id' => $teacher->id, 'date' => '2026-11-23', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'pending']);
    expect(app(DeterministicSchedulingEngine::class)->generate($draft)['alternatives_count'])->toBe(0);
});

test('g303 respects room capacity maintenance department and mandatory facilities', function () {
    $data = g303Fixture();
    $room = $data['rooms'][0];
    $draft = g303Draft([
        'total_meetings' => 1, 'end_date' => '2026-11-25',
        'preferred_time_window' => ['start' => '08:00', 'end' => '10:00'],
        'required_competency_ids' => [$data['competency']->id],
    ]);
    $room->update(['capacity' => 8]);
    expect(app(DeterministicSchedulingEngine::class)->generate($draft)['alternatives_count'])->toBe(0);
    $room->update(['capacity' => 30, 'status' => 'maintenance']);
    expect(app(DeterministicSchedulingEngine::class)->generate($draft)['alternatives_count'])->toBe(0);
    $room->update(['status' => 'available']);
    $facility = Facility::create(['code' => 'G303-LAN', 'name' => 'Jaringan', 'category' => 'network', 'is_active' => true]);
    $draft['required_facility_codes'] = [$facility->code];
    expect(app(DeterministicSchedulingEngine::class)->generate($draft)['alternatives_count'])->toBe(0);
    $room->facilities()->attach($facility->id, ['quantity' => 1, 'condition' => 'good']);
    expect(app(DeterministicSchedulingEngine::class)->generate($draft)['alternatives_count'])->toBeGreaterThan(0);
    $room->update(['department' => 'speakout']);
    expect(app(DeterministicSchedulingEngine::class)->generate($draft)['alternatives_count'])->toBe(0);
});

test('g303 handles curricula with explicit module targets and different teachers for each subject', function () {
    $data = g303Fixture();
    $compB = Competency::create(['name' => 'G303 JavaScript', 'department' => 'computer', 'is_active' => true]);
    $teacherB = Instructor::factory()->create(['department' => 'computer', 'status' => 'active']);
    InstructorSkill::create(['instructor_id' => $teacherB->id, 'competency_id' => $compB->id, 'skill' => $compB->name, 'level' => 'advanced']);
    foreach (['Senin', 'Rabu', 'Jumat'] as $day) {
        InstructorAvailability::create(['instructor_id' => $teacherB->id, 'day' => $day, 'start_time' => '08:00', 'end_time' => '12:00']);
    }

    $program = Program::create(['code' => 'G303-PROG', 'name' => 'Web', 'department' => 'computer', 'is_active' => true]);
    $version = CurriculumVersion::create(['program_id' => $program->id, 'version_code' => 'G303.1', 'status' => 'published']);
    $period = CurriculumPeriod::create(['curriculum_version_id' => $version->id, 'name' => 'Level 1', 'sort_order' => 1]);
    $first = CurriculumSubject::create(['curriculum_period_id' => $period->id, 'competency_id' => $data['competency']->id, 'name' => 'HTML', 'sessions_per_week' => 1, 'sort_order' => 1]);
    $second = CurriculumSubject::create(['curriculum_period_id' => $period->id, 'competency_id' => $compB->id, 'name' => 'JavaScript', 'sessions_per_week' => 2, 'sort_order' => 2]);
    CurriculumModule::create(['curriculum_subject_id' => $first->id, 'title' => 'Struktur HTML', 'target_completed_meetings' => 1, 'sort_order' => 1]);
    CurriculumModule::create(['curriculum_subject_id' => $second->id, 'title' => 'DOM', 'target_completed_meetings' => 2, 'sort_order' => 1]);

    $draft = g303Draft([
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
        'total_meetings' => 3,
        'frequency_per_week' => 3,
        'preferred_weekdays' => ['Senin', 'Rabu', 'Jumat'],
        'end_date' => '2026-11-30',
        'preferred_time_window' => ['start' => '08:00', 'end' => '10:00'],
    ]);
    $result = app(DeterministicSchedulingEngine::class)->generate($draft);
    expect($result['alternatives_count'])->toBeGreaterThan(0);
    $slots = $result['options'][0]['proposed_schedules_payload'];
    expect(array_column($slots, 'curriculum_subject_id'))->toBe([$first->id, $second->id, $second->id])
        ->and(array_column($slots, 'instructor_id'))->toBe([$data['instructors'][0]->id, $teacherB->id, $teacherB->id]);
    $draft['total_meetings'] = 4;
    $result = app(DeterministicSchedulingEngine::class)->generate($draft);
    expect($result['alternatives_count'])->toBe(0)
        ->and(implode(' ', $result['reasons']))->toContain('Alokasi modul kurikulum');
});

test('g303 returns explicit zero option when calendar is too short or competency unknown', function () {
    $data = g303Fixture();
    $draft = g303Draft([
        'total_meetings' => 8,
        'end_date' => '2026-11-27',
        'required_competency_ids' => [$data['competency']->id],
    ]);
    $result = app(DeterministicSchedulingEngine::class)->generate($draft);
    expect($result['alternatives_count'])->toBe(0)
        ->and(implode(' ', $result['reasons']))->toContain('tidak cukup');
    $draft['total_meetings'] = 1;
    $draft['required_competency_ids'] = [];
    $result = app(DeterministicSchedulingEngine::class)->generate($draft);
    expect($result['alternatives_count'])->toBe(0)
        ->and(implode(' ', $result['reasons']))->toContain('kompetensi');
});
