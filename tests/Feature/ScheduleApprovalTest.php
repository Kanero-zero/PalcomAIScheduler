<?php

use App\Livewire\AiScheduler;
use App\Livewire\ApprovalReview;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use App\Services\Scheduling\ScheduleApprovalService;
use App\Services\Scheduling\SchedulingEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->service = app(ScheduleApprovalService::class);
    $this->engine = app(SchedulingEngine::class);
    $this->admin = User::factory()->admin()->create();
    $this->regularUser = User::factory()->create(['email' => 'regular@palcomtech.ac.id']);
});

test('admin can approve candidate substitution and update schedule instructor', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    // Wahyu's leave
    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    $response = $this->service->approveSubstitution(
        leaveId: $leave->id,
        scheduleId: $schedule->id,
        replacementInstructorId: $kanero->id,
        notes: 'Disetujui penggantian oleh admin BAAK.',
    );

    expect($response['success'])->toBeTrue()
        ->and($response['data']['status'])->toBe('approved')
        ->and($response['data']['assigned_instructor']['id'])->toBe($kanero->id);

    // Verify DB state
    $schedule->refresh();
    expect($schedule->instructor_id)->toBe($kanero->id);

    $sub = ScheduleSubstitution::where('instructor_leave_id', $leave->id)
        ->where('schedule_id', $schedule->id)
        ->firstOrFail();

    expect($sub->status)->toBe('approved')
        ->and($sub->original_instructor_id)->toBe($wahyu->id)
        ->and($sub->replacement_instructor_id)->toBe($kanero->id)
        ->and($sub->decision_by)->toBe($this->admin->id)
        ->and($sub->notes)->toBe('Disetujui penggantian oleh admin BAAK.');

    // Status izin induk tetap 'pending' (tidak berubah otomatis)
    $leave->refresh();
    expect($leave->status)->toBe('pending');
});

test('admin can reject candidate substitution without changing schedule instructor', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    $originalInstructorId = $schedule->instructor_id;

    $response = $this->service->rejectSubstitution(
        leaveId: $leave->id,
        scheduleId: $schedule->id,
        rejectionReason: 'Kandidat pengganti sedang mengikuti rapat kurikulum penting.',
    );

    expect($response['success'])->toBeTrue()
        ->and($response['data']['status'])->toBe('rejected')
        ->and($response['data']['rejection_reason'])->toBe('Kandidat pengganti sedang mengikuti rapat kurikulum penting.');

    // Jadwal TIDAK mengalami perubahan
    $schedule->refresh();
    expect($schedule->instructor_id)->toBe($originalInstructorId);

    // Substitution tercatat di DB
    $sub = ScheduleSubstitution::where('instructor_leave_id', $leave->id)
        ->where('schedule_id', $schedule->id)
        ->firstOrFail();

    expect($sub->status)->toBe('rejected')
        ->and($sub->original_instructor_id)->toBe($wahyu->id)
        ->and($sub->replacement_instructor_id)->toBeNull()
        ->and($sub->decision_by)->toBe($this->admin->id);
});

test('non-admin and guests are denied from approving or rejecting', function () {
    $leave = InstructorLeave::firstOrFail();
    $schedule = Schedule::firstOrFail();
    $instructor = Instructor::firstOrFail();

    // 1. Guest
    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $instructor->id))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $this->service->rejectSubstitution($leave->id, $schedule->id, 'Alasan penolakan valid minimal 10 karakter.'))
        ->toThrow(AuthorizationException::class);

    // 2. Regular user (non-admin)
    $this->actingAs($this->regularUser);

    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $instructor->id))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $this->service->rejectSubstitution($leave->id, $schedule->id, 'Alasan penolakan valid minimal 10 karakter.'))
        ->toThrow(AuthorizationException::class);
});

test('rejection reason must be between 10 and 500 characters', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);
    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Less than 10 characters
    expect(fn () => $this->service->rejectSubstitution($leave->id, $schedule->id, 'Pendek'))
        ->toThrow(ValidationException::class);

    // More than 500 characters
    $longReason = str_repeat('Alasan penolakan yang sangat panjang sekali ', 15);
    expect(fn () => $this->service->rejectSubstitution($leave->id, $schedule->id, $longReason))
        ->toThrow(ValidationException::class);
});

test('it verifies that schedule belongs to the leave', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);

    // Schedule on an explicitly different date
    $existing = Schedule::firstOrFail();
    $unrelatedSchedule = Schedule::create([
        'course_class_id' => $existing->course_class_id,
        'instructor_id' => $wahyu->id,
        'room_id' => $existing->room_id,
        'date' => '2026-12-01',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    expect(fn () => $this->service->approveSubstitution($leave->id, $unrelatedSchedule->id, $kanero->id))
        ->toThrow(ValidationException::class);
});

test('it blocks candidate with scheduling conflict or insufficient competency', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);
    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Dina Oktavia does not have Microsoft Excel skill in seed
    $dina = Instructor::where('name', 'Dina Oktavia')->firstOrFail();

    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $dina->id))
        ->toThrow(ValidationException::class);
});

test('it blocks approval if room requires room change or has room issue for MVP', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);
    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Set schedule's room status to maintenance
    $schedule->room->update(['status' => 'maintenance']);

    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id))
        ->toThrow(ValidationException::class);
});

test('it prevents double booking across schedules and duplicate decisions', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);
    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // First approval succeeds
    $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id);

    // Duplicate approval must be rejected (idempotency)
    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id))
        ->toThrow(ValidationException::class);

    // Duplicate rejection must also be rejected
    expect(fn () => $this->service->rejectSubstitution($leave->id, $schedule->id, 'Alasan penolakan kedua yang tidak boleh.'))
        ->toThrow(ValidationException::class);
});

test('approved schedule remains tracked and does not vanish upon evaluation reload', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);

    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Approve substitution
    $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id);

    // Now schedules.instructor_id is Kanero, not Wahyu!
    $schedule->refresh();
    expect($schedule->instructor_id)->toBe($kanero->id);

    // Re-evaluate leave using SchedulingEngine (simulating page reload)
    $evaluation = $this->engine->evaluateLeave($leave);

    // The approved schedule MUST still be found in affectedSchedules!
    expect($evaluation->totalAffectedSchedules)->toBeGreaterThan(0);

    $evaluatedSchedule = collect($evaluation->affectedSchedules)->firstWhere('scheduleId', $schedule->id);
    expect($evaluatedSchedule)->not->toBeNull()
        ->and($evaluatedSchedule->approvalStatus)->not->toBeNull()
        ->and($evaluatedSchedule->approvalStatus['is_decided'])->toBeTrue()
        ->and($evaluatedSchedule->approvalStatus['status'])->toBe('approved')
        ->and($evaluatedSchedule->approvalStatus['original_instructor_id'])->toBe($wahyu->id)
        ->and($evaluatedSchedule->approvalStatus['replacement_instructor_id'])->toBe($kanero->id)
        ->and($evaluatedSchedule->approvalStatus['replacement_instructor_name'])->toBe('Kanero')
        ->and($evaluatedSchedule->isResolved())->toBeTrue();
});

test('when one class is approved and another is pending, both display correctly and anti-double booking applies', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();
    $officeClass = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $room = Room::where('status', 'available')->firstOrFail();

    // Create a leave spanning morning and afternoon
    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-20',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    // Schedule 1: 09:00 - 11:00
    $schedule1 = Schedule::create([
        'course_class_id' => $officeClass->id,
        'instructor_id' => $wahyu->id,
        'room_id' => $room->id,
        'date' => '2026-10-20',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'status' => 'scheduled',
    ]);

    // Schedule 2: 13:00 - 15:00
    $schedule2 = Schedule::create([
        'course_class_id' => $officeClass->id,
        'instructor_id' => $wahyu->id,
        'room_id' => $room->id,
        'date' => '2026-10-20',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // Approve only Schedule 1
    $this->service->approveSubstitution($leave->id, $schedule1->id, $kanero->id);

    // Evaluate leave
    $eval = $this->engine->evaluateLeave($leave);

    expect($eval->totalAffectedSchedules)->toBe(2);

    $eval1 = collect($eval->affectedSchedules)->firstWhere('scheduleId', $schedule1->id);
    $eval2 = collect($eval->affectedSchedules)->firstWhere('scheduleId', $schedule2->id);

    expect($eval1->approvalStatus['status'])->toBe('approved')
        ->and($eval1->approvalStatus['replacement_instructor_id'])->toBe($kanero->id)
        ->and($eval2->approvalStatus['status'])->toBe('pending')
        ->and($eval2->approvalStatus['is_decided'])->toBeFalse();
});

test('database transaction failure rolls back substitution and does not leave partial state', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);
    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Hook into schedule updating to throw an exception
    Schedule::saving(function ($model) use ($schedule) {
        if ($model->id === $schedule->id && $model->isDirty('instructor_id')) {
            throw new RuntimeException('Simulated failure during schedule update');
        }
    });

    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id))
        ->toThrow(RuntimeException::class);

    // No substitution should be committed
    $count = ScheduleSubstitution::where('instructor_leave_id', $leave->id)
        ->where('schedule_id', $schedule->id)
        ->count();

    expect($count)->toBe(0);

    // Schedule instructor remains unchanged
    $schedule->refresh();
    expect($schedule->instructor_id)->toBe($wahyu->id);
});

test('Livewire components AiScheduler and ApprovalReview can execute approval and rejection actions', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);
    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Test on AiScheduler
    Livewire::test(AiScheduler::class)
        ->call('selectLeave', $leave->id)
        ->call('approveSubstitution', $leave->id, $schedule->id, $kanero->id, 'Disetujui via Livewire')
        ->assertSuccessful();

    $schedule->refresh();
    expect($schedule->instructor_id)->toBe($kanero->id);

    // Test on ApprovalReview for a rejection on another schedule
    $officeClass = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $room = Room::where('status', 'available')->firstOrFail();

    $schedule2 = Schedule::create([
        'course_class_id' => $officeClass->id,
        'instructor_id' => $wahyu->id,
        'room_id' => $room->id,
        'date' => '2026-10-15',
        'start_time' => '14:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    Livewire::test(ApprovalReview::class)
        ->call('selectLeave', $leave->id)
        ->call('rejectSubstitution', $leave->id, $schedule2->id, 'Alasan penolakan melalui ApprovalReview Livewire')
        ->assertSuccessful();

    $sub2 = ScheduleSubstitution::where('schedule_id', $schedule2->id)->firstOrFail();
    expect($sub2->status)->toBe('rejected');
});

test('it rejects approval and rejection for cancelled schedule', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    $schedule->update(['status' => 'cancelled']);

    // Approval must fail
    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id))
        ->toThrow(ValidationException::class);

    // Rejection must also fail
    expect(fn () => $this->service->rejectSubstitution($leave->id, $schedule->id, 'Alasan penolakan valid minimal 10 karakter.'))
        ->toThrow(ValidationException::class);
});

test('it rejects approval if schedule has no valid room or room is deleted', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Hapus ruangan yang bersangkutan untuk menguji kondisi ruangan tidak ditemukan
    DB::statement('PRAGMA foreign_keys = OFF;');
    Room::where('id', $schedule->room_id)->delete();
    DB::statement('PRAGMA foreign_keys = ON;');

    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id))
        ->toThrow(ValidationException::class);
});

test('it prevents cross-schedule double booking when two overlapping classes try to assign the same candidate', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();
    $officeClass = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $room1 = Room::firstOrFail();
    $room2 = Room::where('id', '!=', $room1->id)->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-25',
        'start_time' => '09:00',
        'end_time' => '12:00',
        'status' => 'pending',
    ]);

    // Schedule 1: 09:00 - 11:00
    $schedule1 = Schedule::create([
        'course_class_id' => $officeClass->id,
        'instructor_id' => $wahyu->id,
        'room_id' => $room1->id,
        'date' => '2026-10-25',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'status' => 'scheduled',
    ]);

    // Schedule 2: 10:00 - 12:00 (overlaps with schedule 1)
    $schedule2 = Schedule::create([
        'course_class_id' => $officeClass->id,
        'instructor_id' => $wahyu->id,
        'room_id' => $room2->id,
        'date' => '2026-10-25',
        'start_time' => '10:00',
        'end_time' => '12:00',
        'status' => 'scheduled',
    ]);

    // Approve Schedule 1 with Kanero
    $this->service->approveSubstitution($leave->id, $schedule1->id, $kanero->id);

    // Attempt to approve overlapping Schedule 2 with same instructor Kanero
    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule2->id, $kanero->id))
        ->toThrow(ValidationException::class);
});

test('it translates database concurrency and unique constraint collisions into clean validation errors', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Insert an existing substitution behind the scenes to simulate race condition where another request finished
    ScheduleSubstitution::create([
        'instructor_leave_id' => $leave->id,
        'schedule_id' => $schedule->id,
        'original_instructor_id' => $wahyu->id,
        'replacement_instructor_id' => $kanero->id,
        'status' => 'approved',
        'decision_by' => $this->admin->id,
        'decision_at' => now(),
    ]);

    // Now attempting to approve or reject hits concurrency check and throws ValidationException
    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->service->rejectSubstitution($leave->id, $schedule->id, 'Alasan penolakan valid minimal 10 karakter.'))
        ->toThrow(ValidationException::class);
});

test('it safely handles query exception unique violation as friendly validation error', function () {
    $this->actingAs($this->admin);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $kanero = Instructor::where('name', 'Kanero')->firstOrFail();

    $leave = InstructorLeave::create([
        'instructor_id' => $wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::where('instructor_id', $wahyu->id)
        ->whereDate('date', '2026-10-15')
        ->firstOrFail();

    // Mock ScheduleSubstitution saving event throwing QueryException with unique constraint violation
    $mockQueryException = new QueryException(
        'sqlite',
        'insert into "schedule_substitutions" ...',
        [],
        new Exception('UNIQUE constraint failed: schedule_substitutions.instructor_leave_id, schedule_substitutions.schedule_id')
    );

    ScheduleSubstitution::saving(function () use ($mockQueryException) {
        throw $mockQueryException;
    });

    expect(fn () => $this->service->approveSubstitution($leave->id, $schedule->id, $kanero->id))
        ->toThrow(ValidationException::class);
});
