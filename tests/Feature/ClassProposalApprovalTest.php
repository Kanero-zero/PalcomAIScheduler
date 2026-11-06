<?php

use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{proposal:ScheduleProposal,option:ScheduleProposalOption,ao:User,instructor:Instructor,room:Room} */
function classApprovalFixture(array $overrides = []): array
{
    $ao = User::factory()->create(['role' => 'ao']);
    $instructor = Instructor::factory()->create(['name' => 'Instruktur Proposal', 'department' => 'computer', 'status' => 'active']);
    $room = Room::factory()->create(['name' => 'Lab Proposal', 'department' => 'computer', 'capacity' => 30, 'status' => 'available']);
    $proposal = ScheduleProposal::create(array_merge([
        'requested_by' => $ao->id,
        'title' => 'Web Programming Operasional',
        'input_mode' => 'ai_assistant',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 15,
        'start_date' => '2027-01-04',
        'end_date' => '2027-01-31',
        'total_meetings' => 2,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 2,
        'preferred_weekdays' => ['Senin', 'Rabu'],
        'preferred_time_window' => ['start' => '16:00', 'end' => '18:00'],
        'status' => 'pending',
        'submitted_at' => '2026-11-05 09:00:00',
    ], $overrides));
    $option = ScheduleProposalOption::create([
        'proposal_id' => $proposal->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            ['meeting_sequence' => 1, 'date' => '2027-01-04', 'start_time' => '16:00:00', 'end_time' => '18:00:00', 'instructor_id' => $instructor->id, 'room_id' => $room->id],
            ['meeting_sequence' => 2, 'date' => '2027-01-06', 'start_time' => '16:00:00', 'end_time' => '18:00:00', 'instructor_id' => $instructor->id, 'room_id' => $room->id],
        ],
        'conflicts_count' => 0,
        'score' => 100,
    ]);
    $proposal->selectOption($option);

    return compact('proposal', 'option', 'ao', 'instructor', 'room');
}

test('BAAK can view pending proposals while AO cannot access approval endpoints', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);

    $this->actingAs($baak)->get(route('class-proposal-approvals.index'))
        ->assertOk()->assertSee('Persetujuan Kelas Baru')->assertSee('Web Programming Operasional')
        ->assertSee('Instruktur Proposal')->assertSee('Lab Proposal');
    $this->actingAs($baak)->get(route('dashboard'))->assertSee('Persetujuan Kelas Baru');
    $this->actingAs($baak)->getJson(route('class-proposal-approvals.list', ['status' => 'pending']))
        ->assertOk()->assertJsonPath('proposals.0.id', $data['proposal']->id);
    $this->actingAs($data['ao'])->get(route('class-proposal-approvals.index'))->assertForbidden();
    $this->actingAs($data['ao'])->get(route('dashboard'))->assertDontSee('Persetujuan Kelas Baru');
    $this->actingAs($data['ao'])->postJson(route('class-proposal-approvals.approve', $data['proposal']), ['idempotency_token' => str_repeat('a', 64)])->assertForbidden();
    $this->actingAs($data['ao'])->postJson(route('class-proposal-approvals.reject', $data['proposal']), ['idempotency_token' => str_repeat('b', 64), 'reason' => 'Proposal perlu diperbaiki.'])->assertForbidden();

    expect($data['proposal']->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('schedule_approval_decisions', 0);
    $this->assertDatabaseCount('schedules', 0);
});

test('valid approval creates decision changes status and writes every official schedule', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);

    $this->actingAs($baak)->postJson(route('class-proposal-approvals.approve', $data['proposal']), [
        'idempotency_token' => str_repeat('c', 64),
        'reason' => 'Seluruh resource valid.',
    ])->assertOk()->assertJsonPath('status', 'approved');

    $this->assertDatabaseHas('schedule_approval_decisions', ['proposal_id' => $data['proposal']->id, 'decision_by' => $baak->id, 'decision' => 'approved', 'round' => 1]);
    expect($data['proposal']->fresh()->status)->toBe('approved');
    $this->assertDatabaseCount('schedules', 2);
    $this->assertDatabaseHas('schedules', ['schedule_proposal_id' => $data['proposal']->id, 'meeting_sequence' => 1, 'instructor_id' => $data['instructor']->id, 'room_id' => $data['room']->id, 'status' => 'scheduled']);
});

test('approved proposal displays linked official schedules instead of a mutable option snapshot', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);

    $this->actingAs($baak)->postJson(route('class-proposal-approvals.approve', $data['proposal']), [
        'idempotency_token' => str_repeat('n', 64),
    ])->assertOk();
    $data['option']->update(['proposed_schedules_payload' => [[
        'meeting_sequence' => 1,
        'date' => '2035-12-31',
        'start_time' => '01:00:00',
        'end_time' => '02:00:00',
        'instructor_id' => $data['instructor']->id,
        'room_id' => $data['room']->id,
    ]]]);

    $this->actingAs($baak)->getJson(route('class-proposal-approvals.show', $data['proposal']))
        ->assertOk()
        ->assertJsonPath('proposal.meetings_source', 'official_schedules')
        ->assertJsonPath('proposal.official_schedule_count', 2)
        ->assertJsonPath('proposal.official_schedule_verified', true)
        ->assertJsonPath('proposal.meetings.0.date', '2027-01-04')
        ->assertJsonPath('proposal.meetings.1.date', '2027-01-06')
        ->assertJsonMissing(['date' => '2035-12-31'])
        ->assertJsonPath('validation.mode', 'official_schedule_integrity')
        ->assertJsonPath('validation.has_conflicts', false);
});

test('a new official conflict blocks approval without partial writes', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);
    Schedule::factory()->create([
        'course_class_id' => CourseClass::factory(),
        'instructor_id' => $data['instructor']->id,
        'room_id' => $data['room']->id,
        'date' => '2027-01-04',
        'start_time' => '16:30:00',
        'end_time' => '17:30:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($baak)->postJson(route('class-proposal-approvals.approve', $data['proposal']), [
        'idempotency_token' => str_repeat('d', 64),
    ])->assertUnprocessable()->assertJsonValidationErrors('approval');

    expect($data['proposal']->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('schedule_approval_decisions', 0);
    $this->assertDatabaseCount('schedules', 1);
});

test('exact availability boundaries in H i s format do not create a false conflict', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);
    InstructorAvailability::create([
        'instructor_id' => $data['instructor']->id,
        'day' => 'Senin',
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
    ]);
    $data['option']->update(['proposed_schedules_payload' => [[
        'meeting_sequence' => 1,
        'date' => '2027-01-04',
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'instructor_id' => $data['instructor']->id,
        'room_id' => $data['room']->id,
    ]]]);

    $this->actingAs($baak)
        ->getJson(route('class-proposal-approvals.preview', $data['proposal']))
        ->assertOk()
        ->assertJsonPath('has_conflicts', false)
        ->assertJsonPath('conflicts', []);
});

test('a slot outside H i s availability boundaries remains blocked', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);
    InstructorAvailability::create([
        'instructor_id' => $data['instructor']->id,
        'day' => 'Senin',
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
    ]);
    $data['option']->update(['proposed_schedules_payload' => [[
        'meeting_sequence' => 1,
        'date' => '2027-01-04',
        'start_time' => '15:00:00',
        'end_time' => '18:00:00',
        'instructor_id' => $data['instructor']->id,
        'room_id' => $data['room']->id,
    ]]]);

    $this->actingAs($baak)
        ->getJson(route('class-proposal-approvals.preview', $data['proposal']))
        ->assertOk()
        ->assertJsonPath('has_conflicts', true)
        ->assertJsonFragment(['type' => 'instructor_unavailable_day_time']);
});

test('valid rejection records reason changes status and creates no schedule', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);
    $reason = 'Kebutuhan kelas belum sesuai kalender akademik.';

    $this->actingAs($baak)->postJson(route('class-proposal-approvals.reject', $data['proposal']), [
        'idempotency_token' => str_repeat('e', 64),
        'reason' => $reason,
    ])->assertOk()->assertJsonPath('status', 'rejected')->assertJsonPath('reason', $reason);

    $this->assertDatabaseHas('schedule_approval_decisions', ['proposal_id' => $data['proposal']->id, 'decision_by' => $baak->id, 'decision' => 'rejected', 'reason' => $reason, 'round' => 1]);
    expect($data['proposal']->fresh()->status)->toBe('rejected');
    $this->assertDatabaseCount('schedules', 0);
});

test('rejection requires a reason and decided proposals cannot be processed again', function () {
    $approved = classApprovalFixture(['title' => 'Proposal Approved']);
    $rejected = classApprovalFixture(['title' => 'Proposal Rejected']);
    $baak = User::factory()->create(['role' => 'baak']);

    $this->actingAs($baak)->postJson(route('class-proposal-approvals.reject', $rejected['proposal']), [
        'idempotency_token' => str_repeat('f', 64), 'reason' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->actingAs($baak)->postJson(route('class-proposal-approvals.approve', $approved['proposal']), ['idempotency_token' => str_repeat('g', 64)])->assertOk();
    $this->actingAs($baak)->postJson(route('class-proposal-approvals.reject', $rejected['proposal']), [
        'idempotency_token' => str_repeat('h', 64), 'reason' => 'Ditolak pada keputusan pertama.',
    ])->assertOk();

    $this->actingAs($baak)->postJson(route('class-proposal-approvals.reject', $approved['proposal']), [
        'idempotency_token' => str_repeat('i', 64), 'reason' => 'Tidak boleh memproses ulang.',
    ])->assertUnprocessable()->assertJsonValidationErrors('proposal');
    $this->actingAs($baak)->postJson(route('class-proposal-approvals.approve', $rejected['proposal']), ['idempotency_token' => str_repeat('j', 64)])
        ->assertUnprocessable()->assertJsonValidationErrors('proposal');

    $this->assertDatabaseCount('schedule_approval_decisions', 2);
    $this->assertDatabaseCount('schedules', 2);
});

test('slots without instructor or room fail closed without fallback resource IDs', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);
    $data['option']->update(['proposed_schedules_payload' => [[
        'date' => '2027-01-04', 'start_time' => '16:00:00', 'end_time' => '18:00:00',
    ]]]);

    $this->actingAs($baak)->getJson(route('class-proposal-approvals.preview', $data['proposal']))
        ->assertOk()->assertJsonPath('has_conflicts', true)
        ->assertJsonFragment(['type' => 'room_missing'])->assertJsonFragment(['type' => 'instructor_missing']);
    $this->actingAs($baak)->postJson(route('class-proposal-approvals.approve', $data['proposal']), [
        'idempotency_token' => str_repeat('k', 64),
    ])->assertUnprocessable()->assertJsonValidationErrors('approval');

    expect($data['proposal']->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('schedule_approval_decisions', 0);
    $this->assertDatabaseCount('schedules', 0);
});

test('approval idempotency returns the original decision without duplicate schedules', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);
    $token = str_repeat('l', 64);

    $first = $this->actingAs($baak)->postJson(route('class-proposal-approvals.approve', $data['proposal']), ['idempotency_token' => $token])->assertOk();
    $second = $this->actingAs($baak)->postJson(route('class-proposal-approvals.approve', $data['proposal']), ['idempotency_token' => $token])->assertOk();

    expect($second->json('decision_id'))->toBe($first->json('decision_id'));
    $this->assertDatabaseCount('schedule_approval_decisions', 1);
    $this->assertDatabaseCount('schedules', 2);
});

test('BAAK can retrieve reviewed history and detailed live validation', function () {
    $data = classApprovalFixture();
    $baak = User::factory()->create(['role' => 'baak']);
    $reason = 'Ditolak untuk pengujian history proposal.';
    $this->actingAs($baak)->postJson(route('class-proposal-approvals.reject', $data['proposal']), [
        'idempotency_token' => str_repeat('m', 64), 'reason' => $reason,
    ])->assertOk();

    $this->actingAs($baak)->getJson(route('class-proposal-approvals.list', ['status' => 'reviewed']))
        ->assertOk()->assertJsonPath('proposals.0.status', 'rejected')->assertJsonPath('proposals.0.decision_reason', $reason);
    $this->actingAs($baak)->getJson(route('class-proposal-approvals.show', $data['proposal']))
        ->assertOk()->assertJsonPath('proposal.id', $data['proposal']->id)
        ->assertJsonPath('proposal.meetings.0.instructor_name', 'Instruktur Proposal')
        ->assertJsonStructure(['validation' => ['has_conflicts', 'conflicts']]);
});
