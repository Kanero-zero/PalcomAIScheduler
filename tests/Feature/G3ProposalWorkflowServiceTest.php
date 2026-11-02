<?php

use App\Models\Competency;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorSkill;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\User;
use App\Services\Scheduling\DeterministicSchedulingEngine;
use App\Services\Scheduling\ProposalWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00', 'Asia/Jakarta')));
afterEach(fn () => Carbon::setTestNow());

function g304Fixture(): array
{
    $ao = User::factory()->create(['role' => 'ao']);
    $competency = Competency::create(['name' => 'G304 HTML', 'department' => 'computer', 'is_active' => true]);
    $instructor = Instructor::factory()->create(['department' => 'computer', 'status' => 'active']);
    InstructorSkill::create(['instructor_id' => $instructor->id, 'competency_id' => $competency->id,
        'skill' => $competency->name, 'level' => 'advanced']);
    foreach (['Senin', 'Rabu'] as $day) {
        InstructorAvailability::create(['instructor_id' => $instructor->id, 'day' => $day,
            'start_time' => '08:00', 'end_time' => '12:00']);
    }
    $room = Room::factory()->create(['department' => 'computer', 'capacity' => 25, 'status' => 'available']);

    return compact('ao', 'competency', 'instructor', 'room');
}

function g304Draft(array $fixture, array $overrides = []): array
{
    return array_replace([
        'input_mode' => 'ai_assistant',
        'title' => 'G304 Kelas Demo',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 12,
        'start_date' => '2026-11-23',
        'end_date' => '2026-12-20',
        'total_meetings' => 4,
        'frequency_per_week' => 2,
        'session_duration_minutes' => 120,
        'preferred_weekdays' => ['Senin', 'Rabu'],
        'preferred_time_window' => ['start' => '08:00', 'end' => '10:00'],
        'preferred_instructor_id' => $fixture['instructor']->id,
        'preferred_room_id' => $fixture['room']->id,
        'required_competency_ids' => [$fixture['competency']->id],
    ], $overrides);
}

test('g304 preview provides server bound token without writing proposal or schedule', function () {
    $fixture = g304Fixture();
    $result = app(ProposalWorkflowService::class)->preview(g304Draft($fixture));
    expect($result['alternatives_count'])->toBe(1)
        ->and($result['options'][0]['option_label'])->toBe('A')
        ->and(strlen($result['options'][0]['preview_token']))->toBe(64)
        ->and($result['options'][0]['proposed_schedules_payload'])->toHaveCount(4)
        ->and(ScheduleProposal::count())->toBe(0)
        ->and(Schedule::count())->toBe(0);
});

test('g304 AO submits verified option and persists all slots as pending without reservation', function () {
    $fixture = g304Fixture();
    $draft = g304Draft($fixture, ['requested_by' => 99999, 'status' => 'approved',
        'selected_option_id' => 987654]);
    $service = app(ProposalWorkflowService::class);
    $preview = $service->preview($draft);
    $selected = $preview['options'][0];
    $before = app(DeterministicSchedulingEngine::class)->generate($draft);
    auth()->login($fixture['ao']);
    $proposal = $service->submit($draft, 'A', $selected['preview_token']);
    expect($proposal->status)->toBe('pending')
        ->and($proposal->requested_by)->toBe($fixture['ao']->id)
        ->and($proposal->input_mode)->toBe('ai_assistant')
        ->and($proposal->selectedOption->proposal_id)->toBe($proposal->id)
        ->and($proposal->selectedOption->proposed_schedules_payload)->toHaveCount(4)
        ->and($proposal->options)->toHaveCount($preview['alternatives_count'])
        ->and(Schedule::count())->toBe(0)
        ->and(app(DeterministicSchedulingEngine::class)->generate($draft))->toBe($before);
});

test('g304 identical AO retry is idempotent while changed option or draft is not silently substituted', function () {
    $fixture = g304Fixture();
    $draft = g304Draft($fixture);
    $service = app(ProposalWorkflowService::class);
    $preview = $service->preview($draft);
    $token = $preview['options'][0]['preview_token'];
    auth()->login($fixture['ao']);
    $first = $service->submit($draft, 'A', $token);
    $second = $service->submit($draft, 'A', $token);
    expect($second->id)->toBe($first->id)
        ->and(ScheduleProposal::count())->toBe(1)
        ->and(ScheduleProposalOption::count())->toBe(1);

    try {
        $service->submit(g304Draft($fixture, ['student_count' => 13]), 'A', $token);
        $this->fail('Token dari draft lama harus ditolak');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('preview_token');
    }
    expect(ScheduleProposal::count())->toBe(1);
});

test('g304 rejects a stale preview if a schedule becomes official between preview and submit', function () {
    $fixture = g304Fixture();
    $draft = g304Draft($fixture);
    $service = app(ProposalWorkflowService::class);
    $option = $service->preview($draft)['options'][0];
    $firstSlot = $option['proposed_schedules_payload'][0];
    auth()->login($fixture['ao']);
    Schedule::factory()->create([
        'course_class_id' => CourseClass::factory()->create()->id,
        'instructor_id' => $fixture['instructor']->id,
        'room_id' => $fixture['room']->id,
        'date' => $firstSlot['date'],
        'start_time' => $firstSlot['start_time'],
        'end_time' => $firstSlot['end_time'],
        'status' => 'scheduled',
    ]);
    try {
        $service->submit($draft, 'A', $option['preview_token']);
        $this->fail('Stale token must fail closed');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('preview_token');
    }
    expect(ScheduleProposal::count())->toBe(0)->and(Schedule::count())->toBe(1);
});

test('g304 rejects non AO submit and invalid option tokens without persistence', function () {
    $fixture = g304Fixture();
    $staff = User::factory()->create(['role' => 'staff']);
    $service = app(ProposalWorkflowService::class);
    $draft = g304Draft($fixture);
    $token = $service->preview($draft)['options'][0]['preview_token'];
    auth()->login($staff);
    try {
        $service->submit($draft, 'A', $token);
        $this->fail('Staf tidak berhak submit');
    } catch (AuthorizationException $exception) {
        expect($exception->getMessage())->toContain('AO');
    }
    auth()->login($fixture['ao']);
    foreach ([['C', $token], ['A', str_repeat('a', 64)]] as [$label, $badToken]) {
        try {
            $service->submit($draft, $label, $badToken);
            $this->fail('Invalid option must fail closed');
        } catch (ValidationException $exception) {
            expect($exception->errors())->not->toBeEmpty();
        }
    }
    expect(ScheduleProposal::count())->toBe(0);
});

test('g304 only returns own proposals and selected option under the correct owner', function () {
    $fixture = g304Fixture();
    $otherAo = User::factory()->create(['role' => 'ao']);
    $draft = g304Draft($fixture);
    $service = app(ProposalWorkflowService::class);
    $option = $service->preview($draft)['options'][0];
    auth()->login($fixture['ao']);
    $proposal = $service->submit($draft, 'A', $option['preview_token']);
    expect($service->ownProposals())->toHaveCount(1)
        ->and($service->ownProposalDetail($proposal->id)->options)->toHaveCount(1)
        ->and($service->ownOptionDetail($proposal->id, $proposal->selected_option_id)->id)
        ->toBe($proposal->selected_option_id);

    auth()->login($otherAo);
    expect($service->ownProposals())->toHaveCount(0);
    $this->expectException(ModelNotFoundException::class);
    $service->ownOptionDetail($proposal->id, $proposal->selected_option_id);
});

test('g304 guest cannot submit a proposal', function () {
    $fixture = g304Fixture();
    $draft = g304Draft($fixture);
    $service = app(ProposalWorkflowService::class);
    $token = $service->preview($draft)['options'][0]['preview_token'];
    auth()->logout();
    $this->expectException(AuthenticationException::class);
    $service->submit($draft, 'A', $token);
});

test('g304 selected B snapshot and all generated alternatives are persisted from the server', function () {
    $fixture = g304Fixture();
    Room::factory()->create(['department' => 'computer', 'capacity' => 25, 'status' => 'available']);
    $draft = g304Draft($fixture, ['preferred_room_id' => null, 'input_mode' => 'manual_input']);
    $service = app(ProposalWorkflowService::class);
    $preview = $service->preview($draft);
    expect($preview['alternatives_count'])->toBeGreaterThanOrEqual(2);
    $b = collect($preview['options'])->firstWhere('option_label', 'B');
    auth()->login($fixture['ao']);
    $proposal = $service->submit($draft, 'B', $b['preview_token']);
    expect($proposal->input_mode)->toBe('manual_input')
        ->and($proposal->selectedOption->option_label)->toBe('B')
        ->and($proposal->options)->toHaveCount($preview['alternatives_count'])
        ->and($proposal->options->pluck('option_label')->all())->toContain('A', 'B')
        ->and(Schedule::count())->toBe(0);
});
