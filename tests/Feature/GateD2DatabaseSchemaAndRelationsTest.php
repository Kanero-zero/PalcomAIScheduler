<?php

use App\Models\ClassCurriculumSnapshot;
use App\Models\Competency;
use App\Models\CourseClass;
use App\Models\CurriculumModule;
use App\Models\CurriculumPeriod;
use App\Models\CurriculumSubject;
use App\Models\CurriculumVersion;
use App\Models\Facility;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Program;
use App\Models\RecurringPattern;
use App\Models\Room;
use App\Models\RoomFacility;
use App\Models\Schedule;
use App\Models\ScheduleApprovalDecision;
use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\User;
use App\Services\Scheduling\ProposalApprovalConflictValidator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('facilities and room facilities relations with composite unique work as designed', function () {
    $room = Room::factory()->create(['name' => 'Lab Komputer 1', 'department' => 'computer']);

    $pcFacility = Facility::create([
        'code' => 'pc_core_i7',
        'name' => 'PC Core i7 16GB RAM',
        'category' => 'hardware',
        'is_active' => true,
    ]);

    $pivot = RoomFacility::create([
        'room_id' => $room->id,
        'facility_id' => $pcFacility->id,
        'quantity' => 25,
        'condition' => 'good',
    ]);

    expect($room->fresh()->facilities)->toHaveCount(1)
        ->and($room->facilities->first()->code)->toBe('pc_core_i7')
        ->and($room->facilities->first()->pivot->quantity)->toBe(25);

    // Duplikasi fasilitas pada ruangan yang sama harus melanggar composite unique
    expect(fn () => RoomFacility::create([
        'room_id' => $room->id,
        'facility_id' => $pcFacility->id,
        'quantity' => 5,
        'condition' => 'fair',
    ]))->toThrow(QueryException::class);
});

test('academic curriculum V2 hierarchy and composite unique on version work as designed', function () {
    $program = Program::create([
        'code' => 'PROG-WEB-01',
        'name' => 'Web Programming',
        'department' => 'computer',
        'is_active' => true,
    ]);

    $version = CurriculumVersion::create([
        'program_id' => $program->id,
        'version_code' => '2026.1',
        'status' => 'published',
        'published_at' => now(),
    ]);

    // Duplikasi version_code dalam program yang sama harus ditolak
    expect(fn () => CurriculumVersion::create([
        'program_id' => $program->id,
        'version_code' => '2026.1',
        'status' => 'draft',
    ]))->toThrow(QueryException::class);

    // Program lain boleh menggunakan version_code yang sama
    $programSpeakout = Program::create([
        'code' => 'PROG-ENG-01',
        'name' => 'Speakout English',
        'department' => 'speakout',
    ]);

    $versionSpeakout = CurriculumVersion::create([
        'program_id' => $programSpeakout->id,
        'version_code' => '2026.1',
        'status' => 'draft',
    ]);

    expect($versionSpeakout->id)->toBeGreaterThan(0);

    // Periode, Subject, dan Module
    $period = CurriculumPeriod::create([
        'curriculum_version_id' => $version->id,
        'name' => 'Level 1: Frontend Dasar',
        'sort_order' => 1,
    ]);

    $competency = Competency::create([
        'name' => 'HTML & CSS',
        'department' => 'computer',
        'is_active' => true,
    ]);

    $subject = CurriculumSubject::create([
        'curriculum_period_id' => $period->id,
        'competency_id' => $competency->id,
        'name' => 'HTML5 dan CSS3 Modern',
        'sessions_per_week' => 2,
        'sort_order' => 1,
    ]);

    $module = CurriculumModule::create([
        'curriculum_subject_id' => $subject->id,
        'title' => 'Pengenalan Tag Semantik dan Layout Flexbox',
        'target_completed_meetings' => 4,
        'sort_order' => 1,
    ]);

    expect($version->periods)->toHaveCount(1)
        ->and($period->subjects)->toHaveCount(1)
        ->and($subject->modules)->toHaveCount(1)
        ->and($subject->competency->name)->toBe('HTML & CSS');
});

test('course class V2 metadata, snapshots, and recurring patterns work as designed', function () {
    $program = Program::create(['code' => 'PROG-TEST', 'name' => 'Test Program', 'department' => 'computer']);

    $class = CourseClass::create([
        'name' => 'Kelas Kursus Reguler A',
        'subject' => 'Web Design',
        'student_count' => 18,
        'program_id' => $program->id,
        'department' => 'computer',
        'kind' => 'regular',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-28',
        'total_meetings' => 24,
        'session_duration_minutes' => 120,
        'status' => 'planned',
    ]);

    $snapshot = ClassCurriculumSnapshot::create([
        'course_class_id' => $class->id,
        'snapshot_payload' => ['program' => 'Web Design', 'modules_count' => 6],
    ]);

    $pattern = RecurringPattern::create([
        'course_class_id' => $class->id,
        'frequency_per_week' => 2,
        'preferred_weekdays' => ['monday', 'wednesday'],
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'duration_minutes' => 120,
    ]);

    expect($class->fresh()->program->name)->toBe('Test Program')
        ->and($class->curriculumSnapshot->snapshot_payload['modules_count'])->toBe(6)
        ->and($class->recurringPatterns)->toHaveCount(1)
        ->and($class->recurringPatterns->first()->preferred_weekdays)->toBe(['monday', 'wednesday']);
});

test('schedule proposal and options enforce composite unique and strict selected option integrity', function () {
    $ao = User::factory()->create(['role' => 'ao']);

    $proposal = ScheduleProposal::create([
        'requested_by' => $ao->id,
        'title' => 'Pengajuan Jadwal Kelas Web Baru',
        'input_mode' => 'ai_assistant',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 15,
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-28',
        'total_meetings' => 24,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 2,
        'preferred_weekdays' => ['monday', 'wednesday'],
        'status' => 'pending',
    ]);

    $optionA = ScheduleProposalOption::create([
        'proposal_id' => $proposal->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            ['date' => '2026-11-02', 'start_time' => '09:00:00', 'end_time' => '11:00:00'],
        ],
        'conflicts_count' => 0,
        'score' => 95,
    ]);

    // Duplikasi option_label pada proposal yang sama harus ditolak
    expect(fn () => ScheduleProposalOption::create([
        'proposal_id' => $proposal->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [],
    ]))->toThrow(QueryException::class);

    // Opsi A berhasil dipilih
    $proposal->selectOption($optionA);
    expect($proposal->fresh()->selected_option_id)->toBe($optionA->id)
        ->and($proposal->selectedOption->option_label)->toBe('A');

    // Buat proposal lain
    $otherProposal = ScheduleProposal::create([
        'requested_by' => $ao->id,
        'title' => 'Proposal Kedua',
        'department' => 'computer',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-28',
        'total_meetings' => 24,
    ]);

    $otherOption = ScheduleProposalOption::create([
        'proposal_id' => $otherProposal->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [],
    ]);

    // Integritas ketat: Mencoba memilih opsi milik proposal lain harus ditolak dengan InvalidArgumentException!
    expect(fn () => $proposal->selectOption($otherOption))
        ->toThrow(InvalidArgumentException::class);
});

test('schedule approval decisions support sequential rounds for resubmission history', function () {
    $ao = User::factory()->create(['role' => 'ao']);
    $baak = User::factory()->create(['role' => 'baak']);

    $proposal = ScheduleProposal::create([
        'requested_by' => $ao->id,
        'title' => 'Proposal Kelas Kursus',
        'department' => 'computer',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-28',
        'total_meetings' => 24,
        'status' => 'pending',
    ]);

    // Putaran 1: BAAK meminta revisi
    $decisionRound1 = ScheduleApprovalDecision::create([
        'proposal_id' => $proposal->id,
        'round' => 1,
        'decision_by' => $baak->id,
        'decision' => 'needs_revision',
        'reason' => 'Mohon sesuaikan jam karena lab digunakan ujian.',
        'idempotency_token' => 'token-round-1',
        'decided_at' => now()->subDay(),
    ]);

    $proposal->update(['status' => 'needs_revision']);

    // AO revisi dan submit ulang -> Putaran 2
    $proposal->update(['status' => 'pending']);

    $decisionRound2 = ScheduleApprovalDecision::create([
        'proposal_id' => $proposal->id,
        'round' => 2,
        'decision_by' => $baak->id,
        'decision' => 'approved',
        'reason' => 'Jadwal revisi telah terkonfirmasi dan disetujui.',
        'idempotency_token' => 'token-round-2',
        'decided_at' => now(),
    ]);

    $proposal->update(['status' => 'approved']);

    expect($proposal->decisions)->toHaveCount(2)
        ->and($proposal->latestDecision->round)->toBe(2)
        ->and($proposal->latestDecision->decision)->toBe('approved');

    // Duplikasi round pada proposal yang sama harus ditolak
    expect(fn () => ScheduleApprovalDecision::create([
        'proposal_id' => $proposal->id,
        'round' => 2,
        'decision_by' => $baak->id,
        'decision' => 'rejected',
        'idempotency_token' => 'token-duplicate-round',
    ]))->toThrow(QueryException::class);
});

test('proposal approval conflict validator catches real time resource overlaps', function () {
    $validator = app(ProposalApprovalConflictValidator::class);

    $ao = User::factory()->create(['role' => 'ao']);
    $instructor = Instructor::factory()->create(['status' => 'active']);
    $room = Room::factory()->create(['status' => 'available', 'capacity' => 20]);
    $class = CourseClass::factory()->create(['name' => 'Kelas Aktif', 'subject' => 'Web Design']);

    $proposal = ScheduleProposal::create([
        'requested_by' => $ao->id,
        'title' => 'Proposal Validasi Real-Time',
        'department' => 'computer',
        'student_count' => 15,
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-02',
        'total_meetings' => 1,
    ]);

    $option = ScheduleProposalOption::create([
        'proposal_id' => $proposal->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            [
                'date' => '2026-11-02',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
            ],
        ],
    ]);

    // Kondisi 1: Tidak ada konflik -> lolos
    $result = $validator->validateOption($proposal, $option, failClosed: false);
    expect($result['has_conflicts'])->toBeFalse();

    // Kondisi 2: Instruktur mengambil cuti mendadak di slot yang sama
    InstructorLeave::create([
        'instructor_id' => $instructor->id,
        'date' => '2026-11-02',
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'status' => 'pending',
    ]);

    $resultWithLeave = $validator->validateOption($proposal, $option, failClosed: false);
    expect($resultWithLeave['has_conflicts'])->toBeTrue()
        ->and($resultWithLeave['conflicts'][0]['type'])->toBe('instructor_on_leave');

    // Jika failClosed = true -> lempar ValidationException
    expect(fn () => $validator->validateOption($proposal, $option, failClosed: true))
        ->toThrow(ValidationException::class);
});

test('selected option integrity is strictly enforced via saving event hook even on direct update', function () {
    $ao = User::factory()->create(['role' => 'ao']);

    $proposal1 = ScheduleProposal::create([
        'requested_by' => $ao->id,
        'title' => 'Proposal 1',
        'department' => 'computer',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-28',
        'total_meetings' => 24,
    ]);

    $proposal2 = ScheduleProposal::create([
        'requested_by' => $ao->id,
        'title' => 'Proposal 2',
        'department' => 'computer',
        'start_date' => '2026-11-02',
        'end_date' => '2027-01-28',
        'total_meetings' => 24,
    ]);

    $optionOfProposal2 = ScheduleProposalOption::create([
        'proposal_id' => $proposal2->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [],
    ]);

    // Percobaan mengubah selected_option_id secara langsung via update() atau assignment harus ditolak oleh booted saving hook!
    expect(fn () => $proposal1->update(['selected_option_id' => $optionOfProposal2->id]))
        ->toThrow(InvalidArgumentException::class);

    expect(function () use ($proposal1, $optionOfProposal2) {
        $proposal1->selected_option_id = $optionOfProposal2->id;
        $proposal1->save();
    })->toThrow(InvalidArgumentException::class);
});

test('executeApprovalWithLock runs atomically with concurrency locks and idempotency token', function () {
    $validator = app(ProposalApprovalConflictValidator::class);

    $ao = User::factory()->create(['role' => 'ao']);
    $baak = User::factory()->create(['role' => 'baak']);
    $instructor = Instructor::factory()->create(['status' => 'active', 'department' => 'computer']);
    $room = Room::factory()->create(['status' => 'available', 'department' => 'computer', 'capacity' => 20]);

    $proposal = ScheduleProposal::create([
        'requested_by' => $ao->id,
        'title' => 'Kelas Kursus Fullstack Web',
        'department' => 'computer',
        'student_count' => 15,
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-02',
        'total_meetings' => 1,
        'status' => 'pending',
    ]);

    $option = ScheduleProposalOption::create([
        'proposal_id' => $proposal->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            [
                'date' => '2026-11-02',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
            ],
        ],
    ]);

    $token = 'idempotent-token-approval-123';

    // Eksekusi persetujuan pertama
    $decision = $validator->executeApprovalWithLock($proposal, $option, $baak, $token, 'Disetujui untuk semester depan.');

    expect($decision->id)->toBeGreaterThan(0)
        ->and($decision->decision)->toBe('approved')
        ->and($proposal->fresh()->status)->toBe('approved')
        ->and($proposal->fresh()->selected_option_id)->toBe($option->id);

    // Cek record jadwal tertulis di tabel schedules
    $createdSchedule = Schedule::whereDate('date', '2026-11-02')->where('room_id', $room->id)->first();
    expect($createdSchedule)->not->toBeNull()
        ->and($createdSchedule->status)->toBe('scheduled')
        ->and($createdSchedule->instructor_id)->toBe($instructor->id);

    // Eksekusi kedua dengan token yang sama harus idempoten (mengembalikan decision yang sama tanpa double-booking)
    $decisionDuplicate = $validator->executeApprovalWithLock($proposal, $option, $baak, $token);
    expect($decisionDuplicate->id)->toBe($decision->id);

    // Pastikan tidak ada duplikasi jadwal di schedules
    expect(Schedule::whereDate('date', '2026-11-02')->where('room_id', $room->id)->count())->toBe(1);
});

test('all 16 Gate D2 migrations can be rolled back and re-migrated cleanly without syntax or integrity errors', function () {
    // Jalankan rollback 16 migrasi terbaru
    $rollbackExit = Artisan::call('migrate:rollback', ['--step' => 16]);
    expect($rollbackExit)->toBe(0);

    // Pastikan tabel-tabel Gate D2 telah dihapus saat rollback
    expect(Schema::hasTable('schedule_approval_decisions'))->toBeFalse()
        ->and(Schema::hasTable('schedule_proposal_options'))->toBeFalse()
        ->and(Schema::hasTable('schedule_proposals'))->toBeFalse()
        ->and(Schema::hasTable('curriculum_modules'))->toBeFalse()
        ->and(Schema::hasTable('facilities'))->toBeFalse();

    // Jalankan migrasi kembali (up)
    $migrateExit = Artisan::call('migrate');
    expect($migrateExit)->toBe(0);

    // Pastikan seluruh tabel Gate D2 kembali terbuat dengan sempurna
    expect(Schema::hasTable('facilities'))->toBeTrue()
        ->and(Schema::hasTable('room_facilities'))->toBeTrue()
        ->and(Schema::hasTable('programs'))->toBeTrue()
        ->and(Schema::hasTable('curriculum_versions'))->toBeTrue()
        ->and(Schema::hasTable('schedule_proposals'))->toBeTrue()
        ->and(Schema::hasTable('schedule_proposal_options'))->toBeTrue()
        ->and(Schema::hasTable('schedule_approval_decisions'))->toBeTrue();
});
