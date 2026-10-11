<?php

use App\Livewire\ApprovalReview;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/** @return array{leave: InstructorLeave, schedules: array<Schedule>, candidate: Instructor, invalid: Instructor} */
function approvalReviewScenario(): array
{
    $instructor = Instructor::factory()->create(['name' => 'Instruktur Izin']);
    $candidate = Instructor::factory()->create(['name' => 'Pengganti Valid']);
    $candidate->skills()->create(['skill' => 'Microsoft Excel', 'level' => 'advanced']);
    $invalid = Instructor::factory()->create(['name' => 'Tanpa Kompetensi']);
    $leave = InstructorLeave::factory()->for($instructor)->create([
        'date' => '2026-10-20', 'start_time' => '08:00', 'end_time' => '17:00', 'status' => 'pending',
    ]);
    $courseClass = CourseClass::factory()->create(['name' => 'Excel Pagi', 'subject' => 'Microsoft Excel', 'student_count' => 10]);
    $room = Room::factory()->create(['capacity' => 20]);
    $schedules = [];
    foreach ([['09:00', '11:00'], ['13:00', '15:00']] as [$start, $end]) {
        $schedules[] = Schedule::factory()->for($instructor)->for($courseClass)->for($room)->create([
            'date' => '2026-10-20', 'start_time' => $start, 'end_time' => $end,
        ]);
    }

    return compact('leave', 'schedules', 'candidate', 'invalid');
}

it('requires login to open Approval and Reject', function () {
    $this->get(route('approval-review'))->assertRedirect(route('login'));
});

it('shows real leave data and confirmation controls to admins', function () {
    $scenario = approvalReviewScenario();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('approval-review'))
        ->assertSeeLivewire(ApprovalReview::class)
        ->assertSee('Instruktur Izin')
        ->assertSee('Excel Pagi')
        ->assertSee('Pilih Instruktur Pengganti')
        ->assertSee('Konfirmasi Persetujuan')
        ->assertSee('Konfirmasi Penolakan')
        ->assertDontSee('DEMO-001')
        ->assertDontSee('Data simulasi');

    Livewire::test(ApprovalReview::class)
        ->assertSet('selectedLeaveId', $scenario['leave']->id)
        ->assertSet('result.affected_schedules.0.valid_candidates.0.instructor_id', $scenario['candidate']->id)
        ->assertSet('result.affected_schedules.0.disqualified_candidates.0.instructor_id', $scenario['invalid']->id)
        ->assertSet('result.affected_schedules.0.disqualified_candidates.0.disqualification_reason', fn ($reason) => filled($reason));
});

it('requires verification for the approval route', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('approval-review'))->assertRedirect(route('verification.notice'));
});

it('selects and decides each schedule independently and restores decisions after reload', function () {
    $scenario = approvalReviewScenario();
    $admin = User::factory()->admin()->create();
    [$first, $second] = $scenario['schedules'];
    $this->actingAs($admin);
    $this->freezeTime();

    Livewire::test(ApprovalReview::class)
        ->call('selectSchedule', $second->id)
        ->assertSet('selectedScheduleId', $second->id)
        ->call('approveSubstitution', $scenario['leave']->id, $second->id, $scenario['candidate']->id)
        ->assertHasNoErrors()
        ->assertSee('Instruktur pengganti berhasil disetujui dan jadwal kelas telah diperbarui.')
        ->assertSet('selectedScheduleId', $second->id)
        ->assertSet('result.affected_schedules.0.approval_status.status', 'pending')
        ->assertSet('result.affected_schedules.1.approval_status.status', 'approved')
        ->call('loadEvaluation')
        ->assertSee('Instruktur pengganti berhasil disetujui dan jadwal kelas telah diperbarui.')
        ->call('selectSchedule', $first->id)
        ->assertSet('feedbackMessage', null)
        ->call('rejectSubstitution', $scenario['leave']->id, $first->id, 'Perlu peninjauan jadwal kembali.')
        ->assertHasNoErrors()
        ->assertSee('Rekomendasi pengganti berhasil ditolak.');

    $this->assertDatabaseHas('schedules', ['id' => $second->id, 'instructor_id' => $scenario['candidate']->id]);
    $this->assertDatabaseHas('schedules', ['id' => $first->id, 'instructor_id' => $scenario['leave']->instructor_id]);
    $this->assertDatabaseHas('instructor_leaves', ['id' => $scenario['leave']->id, 'status' => 'pending']);
    $this->assertDatabaseHas('schedule_substitutions', [
        'schedule_id' => $first->id, 'status' => 'rejected', 'decision_by' => $admin->id,
        'rejection_reason' => 'Perlu peninjauan jadwal kembali.',
    ]);
    Livewire::test(ApprovalReview::class)
        ->assertSet('result.affected_schedules.0.approval_status.status', 'rejected')
        ->assertSet('result.affected_schedules.0.approval_status.rejection_reason', 'Perlu peninjauan jadwal kembali.')
        ->assertSet('result.affected_schedules.1.approval_status.status', 'approved')
        ->assertSet('result.affected_schedules.1.approval_status.replacement_instructor_name', 'Pengganti Valid')
        ->assertSet('result.affected_schedules.1.approval_status.decision_by_name', $admin->name)
        ->assertSet('result.affected_schedules.1.approval_status.decision_at', now()->toIso8601String());
});

it('resets the selected schedule when switching leaves and refuses unrelated schedules', function () {
    $scenario = approvalReviewScenario();
    $emptyLeave = InstructorLeave::factory()->create(['date' => '2026-11-01']);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ApprovalReview::class)
        ->call('selectLeave', $scenario['leave']->id)
        ->call('selectSchedule', $scenario['schedules'][1]->id)
        ->call('selectLeave', $emptyLeave->id)
        ->assertSet('selectedScheduleId', null)
        ->assertSee('Tidak ada kelas terdampak pada pengajuan izin ini.')
        ->call('selectSchedule', $scenario['schedules'][0]->id)
        ->assertHasErrors('schedule_id');
});

it('shows a clear empty state without any leave', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ApprovalReview::class)
        ->assertSet('selectedScheduleId', null)
        ->assertSee('Belum ada pengajuan izin yang dapat ditinjau.');
});

it('shows no valid candidate when all candidates are disqualified', function () {
    $scenario = approvalReviewScenario();
    $scenario['candidate']->skills()->delete();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ApprovalReview::class)
        ->assertSet('result.affected_schedules.0.valid_candidates', [])
        ->assertSee('Tidak ada kandidat valid untuk jadwal ini.');
});

it('displays rejection validation without saving a decision', function (string $reason) {
    $scenario = approvalReviewScenario();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ApprovalReview::class)
        ->call('rejectSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, $reason)
        ->assertHasErrors('rejection_reason')
        ->assertSee('Alasan penolakan');

    $this->assertDatabaseCount('schedule_substitutions', 0);
})->with(['blank' => '   ', 'short' => 'Pendek', 'long' => str_repeat('a', 501)]);

it('surfaces invalid candidate validation from the service', function () {
    $scenario = approvalReviewScenario();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ApprovalReview::class)
        ->call('approveSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, $scenario['invalid']->id)
        ->assertHasErrors('replacement_instructor_id')
        ->assertSee('Instruktur yang dipilih tidak memenuhi kualifikasi kompetensi kelas ini.');

    $this->assertDatabaseCount('schedule_substitutions', 0);
});

it('revalidates a candidate that becomes busy after the page loads', function () {
    $scenario = approvalReviewScenario();
    $this->actingAs(User::factory()->admin()->create());
    $page = Livewire::test(ApprovalReview::class);
    Schedule::factory()->for($scenario['candidate'], 'instructor')->create([
        'date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '11:00',
    ]);

    $page->call('approveSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, $scenario['candidate']->id)
        ->assertHasErrors('replacement_instructor_id')
        ->assertSee('Instruktur pengganti memiliki bentrok jadwal');

    $this->assertDatabaseCount('schedule_substitutions', 0);
});

it('displays room validation without changing the schedule', function () {
    $scenario = approvalReviewScenario();
    $scenario['schedules'][0]->room->update(['status' => 'maintenance']);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ApprovalReview::class)
        ->assertSee('Persetujuan ditunda:')
        ->call('approveSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, $scenario['candidate']->id)
        ->assertHasErrors('room')
        ->assertSee('Selesaikan kendala ruangan');

    $this->assertDatabaseCount('schedule_substitutions', 0);
});

it('refuses repeated decisions and retains the saved decision', function () {
    $scenario = approvalReviewScenario();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ApprovalReview::class)
        ->call('approveSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, $scenario['candidate']->id)
        ->call('approveSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, $scenario['candidate']->id)
        ->assertHasErrors('schedule_id')
        ->assertSet('feedbackMessage', null)
        ->call('rejectSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, 'Tidak boleh diputuskan kembali.')
        ->assertHasErrors('schedule_id')
        ->assertSee('sudah memiliki keputusan')
        ->assertSet('result.affected_schedules.0.approval_status.status', 'approved');

    $this->assertDatabaseCount('schedule_substitutions', 1);
});

it('shows a friendly database lock error and permits a later retry', function () {
    $scenario = approvalReviewScenario();
    $this->actingAs(User::factory()->admin()->create());
    $page = Livewire::test(ApprovalReview::class);
    $locked = true;
    ScheduleSubstitution::saving(function () use (&$locked): void {
        if ($locked) {
            throw new QueryException('sqlite', 'insert into schedule_substitutions', [], new Exception('table is locked'));
        }
    });

    try {
        $page->call('rejectSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, 'Perlu peninjauan jadwal kembali.')
            ->assertHasErrors('schedule_id')
            ->assertSee('Sistem sedang sibuk memproses transaksi lain pada database.');
    } finally {
        $locked = false;
        ScheduleSubstitution::flushEventListeners();
    }

    $this->assertDatabaseCount('schedule_substitutions', 0);
    $page->call('rejectSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, 'Perlu peninjauan jadwal kembali.')
        ->assertHasNoErrors()
        ->assertSet('result.affected_schedules.0.approval_status.status', 'rejected');
});

it('allows non-admin viewing but forbids direct approval and rejection calls', function (string $action, mixed $value) {
    $scenario = approvalReviewScenario();
    $this->actingAs(User::factory()->create());

    Livewire::test(ApprovalReview::class)
        ->assertSee('Hanya admin berwenang')
        ->assertDontSee('Setujui Rekomendasi')
        ->call($action, $scenario['leave']->id, $scenario['schedules'][0]->id, $value === 'candidate' ? $scenario['candidate']->id : $value)
        ->assertForbidden();

    $this->assertDatabaseCount('schedule_substitutions', 0);
})->with([
    'approve' => ['approveSubstitution', 'candidate'],
    'reject' => ['rejectSubstitution', 'Alasan penolakan cukup panjang.'],
]);

it('rechecks admin authorization after the page has loaded', function () {
    $scenario = approvalReviewScenario();
    $this->actingAs(User::factory()->admin()->create());
    $page = Livewire::test(ApprovalReview::class);
    config(['auth.admin_emails' => []]);

    $page->call('rejectSubstitution', $scenario['leave']->id, $scenario['schedules'][0]->id, 'Perlu peninjauan jadwal kembali.')
        ->assertForbidden();

    $this->assertDatabaseCount('schedule_substitutions', 0);
});
