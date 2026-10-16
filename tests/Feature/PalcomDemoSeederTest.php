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
use App\Models\InstructorAvailability;
use App\Models\InstructorLeave;
use App\Models\InstructorSkill;
use App\Models\Program;
use App\Models\RecurringPattern;
use App\Models\Room;
use App\Models\RoomFacility;
use App\Models\Schedule;
use App\Models\ScheduleApprovalDecision;
use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use App\Services\Scheduling\ProposalApprovalConflictValidator;
use Database\Seeders\PalcomDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('palcom demo seeder populates all 12 demo scenarios accurately with canonical DEMO tagging', function () {
    $this->seed(PalcomDemoSeeder::class);

    // DEMO-01: Akun Pengguna & RBAC Eksplisit
    expect(User::where('email', 'admin@palcomtech.ac.id')->first())->not->toBeNull()
        ->and(User::where('email', 'baak@palcomtech.ac.id')->first()?->role)->toBe('baak')
        ->and(User::where('email', 'ao@palcomtech.ac.id')->first()?->role)->toBe('ao')
        ->and(User::where('email', 'staff@palcomtech.ac.id')->first()?->role)->toBe('staff')
        ->and(User::where('role', 'instructor')->count())->toBe(4);

    // DEMO-02: Master Fasilitas & Ruangan Terstruktur
    expect(Facility::count())->toBe(6)
        ->and(Room::count())->toBe(6)
        ->and(RoomFacility::count())->toBe(15);
    $lab1 = Room::where('name', 'Lab Komputer 1')->first();
    expect($lab1->department)->toBe('computer')
        ->and($lab1->capacity)->toBe(25)
        ->and($lab1->notes)->toContain('[DEMO-02]')
        ->and($lab1->facilities)->toHaveCount(3);

    // DEMO-03: Master Program & Kurikulum Modular V2
    expect(Competency::count())->toBe(6)
        ->and(Program::count())->toBe(2)
        ->and(CurriculumVersion::count())->toBe(2)
        ->and(CurriculumPeriod::count())->toBe(2)
        ->and(CurriculumSubject::count())->toBe(3)
        ->and(CurriculumModule::count())->toBe(5);

    $htmlSubject = CurriculumSubject::where('name', 'HTML5 & CSS3 Dasar')->first();
    expect($htmlSubject->modules)->toHaveCount(2)
        ->and($htmlSubject->modules->first()->target_completed_meetings)->toBe(2);

    // DEMO-04: Master Instruktur, Akun Login & Matriks Kompetensi
    $wahyu = Instructor::where('name', 'Wahyu Pratama')->first();
    expect($wahyu->user_id)->not->toBeNull()
        ->and($wahyu->notes)->toContain('[DEMO-04]')
        ->and($wahyu->user->email)->toBe('wahyu@palcomtech.ac.id')
        ->and($wahyu->skills)->toHaveCount(3)
        ->and(InstructorAvailability::where('instructor_id', $wahyu->id)->count())->toBe(10); // 5 hari x 2 slot

    // DEMO-05: Kelas Kursus Aktif V2, Snapshot Kurikulum & Recurring Pattern
    $activeClass = CourseClass::where('name', '[DEMO-05] Web Programming Pagi 2026-A')->first();
    expect($activeClass)->not->toBeNull()
        ->and($activeClass->department)->toBe('computer')
        ->and($activeClass->student_count)->toBe(18)
        ->and($activeClass->total_meetings)->toBe(24);

    $snapshot = ClassCurriculumSnapshot::where('course_class_id', $activeClass->id)->first();
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->snapshot_payload['program_code'])->toBe('PROG-WEB-01')
        ->and($snapshot->snapshot_payload['subjects'])->toHaveCount(2);

    $recurring = RecurringPattern::where('course_class_id', $activeClass->id)->first();
    expect($recurring)->not->toBeNull()
        ->and($recurring->preferred_weekdays)->toBe(['monday', 'wednesday'])
        ->and($recurring->start_time)->toBe('09:00:00');

    // DEMO-06: Jadwal Pertemuan Resmi Multi-Materi (Completed & Scheduled)
    $schedules = Schedule::where('course_class_id', $activeClass->id)->orderBy('meeting_sequence')->get();
    expect($schedules)->toHaveCount(6)
        ->and($schedules[0]->status)->toBe('completed')
        ->and($schedules[1]->status)->toBe('completed')
        ->and($schedules[2]->status)->toBe('scheduled')
        ->and($schedules[4]->curriculumSubject->name)->toBe('JavaScript Modern');

    // DEMO-07: Kasus Izin Instruktur & Penggantian Terverifikasi
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->first();
    expect($leave)->not->toBeNull()
        ->and($leave->status)->toBe('pending')
        ->and($leave->reason)->toContain('[DEMO-07]')
        ->and($leave->substitutions)->toHaveCount(1);

    $sub = $leave->substitutions->first();
    expect($sub->original_instructor_id)->toBe($wahyu->id)
        ->and($sub->replacementInstructor->name)->toBe('Kanero Zero')
        ->and($sub->notes)->toContain('[DEMO-07]');

    // DEMO-08: Proposal Penjadwalan AI Assistant (Status Pending)
    $aiProposal = ScheduleProposal::where('input_mode', 'ai_assistant')
        ->where('title', '[DEMO-08] Pengajuan Kelas Desain Web Pagi B (AI Assistant)')
        ->first();
    expect($aiProposal)->not->toBeNull()
        ->and($aiProposal->status)->toBe('pending')
        ->and($aiProposal->options)->toHaveCount(2)
        ->and($aiProposal->selectedOption?->option_label)->toBe('A');

    // DEMO-09: Proposal Penjadwalan Input Manual (Status Pending)
    $manualProposal = ScheduleProposal::where('input_mode', 'manual_input')
        ->where('title', '[DEMO-09] Pengajuan Kelas Speakout Sore Khusus (Input Manual)')
        ->first();
    expect($manualProposal)->not->toBeNull()
        ->and($manualProposal->preferredInstructor->name)->toBe('Sarah Jenkins')
        ->and($manualProposal->status)->toBe('pending')
        ->and($manualProposal->selectedOption?->option_label)->toBe('A');

    // DEMO-10: Proposal Siklus Revisi (Putaran 1 & Putaran 2)
    $revProposal = ScheduleProposal::where('title', '[DEMO-10] Pengajuan Kelas Desain Web Sore Angkatan 2026 (Siklus Revisi)')->first();
    expect($revProposal)->not->toBeNull()
        ->and($revProposal->approvalDecisions)->toHaveCount(1)
        ->and($revProposal->approvalDecisions->first()->decision)->toBe('needs_revision')
        ->and($revProposal->approvalDecisions->first()->reason)->toContain('[DEMO-10]');

    // DEMO-11: Skenario Dua Pengajuan Bersaing pada Slot Sama (Approved vs Pending)
    // Proposal A: Disetujui BAAK dan memiliki jadwal resmi di Ruang Teori 1 pada 2026-12-01 09:00 - 10:30
    $appProposal = ScheduleProposal::where('title', '[DEMO-11] Pengajuan Kelas Speakout Pagi Reguler (Disetujui)')->first();
    expect($appProposal)->not->toBeNull()
        ->and($appProposal->status)->toBe('approved')
        ->and($appProposal->approvalDecisions->first()->decision)->toBe('approved')
        ->and($appProposal->courseClass)->not->toBeNull()
        ->and($appProposal->courseClass->name)->toBe('[DEMO-11] Speakout Pagi Reguler 2026-A');

    $appSchedule = Schedule::where('course_class_id', $appProposal->course_class_id)->first();
    expect($appSchedule)->not->toBeNull()
        ->and($appSchedule->date->format('Y-m-d'))->toBe('2026-12-01')
        ->and($appSchedule->start_time)->toBe('09:00:00')
        ->and($appSchedule->status)->toBe('scheduled');

    // Proposal B: Pesaing slot sama, berstatus pending (tidak mereservasi slot)
    $compProposal = ScheduleProposal::where('title', '[DEMO-11] Pengajuan Kelas Speakout Percakapan Pagi (Pesaing Slot Sama)')->first();
    expect($compProposal)->not->toBeNull()
        ->and($compProposal->status)->toBe('pending');

    // Uji kelayakan: Validasi opsi Proposal B harus mendeteksi bentrok dengan Proposal A
    $validator = app(ProposalApprovalConflictValidator::class);
    $validationResult = $validator->validateOption($compProposal, $compProposal->selectedOption, failClosed: false);
    expect($validationResult['has_conflicts'])->toBeTrue()
        ->and($validationResult['conflicts'][0]['type'])->toBe('room_double_booking');

    // DEMO-12: Proposal Ditolak BAAK (Rejected)
    $rejProposal = ScheduleProposal::where('title', '[DEMO-12] Pengajuan Kelas Ekstra Weekend Minggu (Ditolak)')->first();
    expect($rejProposal)->not->toBeNull()
        ->and($rejProposal->status)->toBe('rejected')
        ->and($rejProposal->approvalDecisions->first()->decision)->toBe('rejected')
        ->and($rejProposal->approvalDecisions->first()->reason)->toContain('[DEMO-12]');
});

test('palcom demo seeder is strictly idempotent on repeated runs with deep data state verification', function () {
    // Jalankan seeder putaran ke-1
    $this->seed(PalcomDemoSeeder::class);

    // Fungsi ekstraksi snapshot mendalam (nilai data, kunci unik, relasi)
    $captureDeepState = function () {
        return [
            'counts' => [
                'users' => User::count(),
                'facilities' => Facility::count(),
                'rooms' => Room::count(),
                'room_facilities' => RoomFacility::count(),
                'competencies' => Competency::count(),
                'programs' => Program::count(),
                'curriculum_versions' => CurriculumVersion::count(),
                'curriculum_periods' => CurriculumPeriod::count(),
                'curriculum_subjects' => CurriculumSubject::count(),
                'curriculum_modules' => CurriculumModule::count(),
                'instructors' => Instructor::count(),
                'instructor_skills' => InstructorSkill::count(),
                'instructor_availabilities' => InstructorAvailability::count(),
                'course_classes' => CourseClass::count(),
                'class_curriculum_snapshots' => ClassCurriculumSnapshot::count(),
                'recurring_patterns' => RecurringPattern::count(),
                'schedules' => Schedule::count(),
                'instructor_leaves' => InstructorLeave::count(),
                'schedule_substitutions' => ScheduleSubstitution::count(),
                'schedule_proposals' => ScheduleProposal::count(),
                'schedule_proposal_options' => ScheduleProposalOption::count(),
                'schedule_approval_decisions' => ScheduleApprovalDecision::count(),
            ],
            'users' => User::orderBy('id')->get(['id', 'email', 'name', 'role'])->toArray(),
            'rooms' => Room::with(['facilities' => fn ($q) => $q->orderBy('facility_id')])->orderBy('id')->get()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'department' => $r->department,
                'capacity' => $r->capacity,
                'status' => $r->status,
                'facilities' => $r->facilities->map(fn ($f) => [
                    'id' => $f->id,
                    'code' => $f->code,
                    'quantity' => $f->pivot->quantity,
                    'condition' => $f->pivot->condition,
                ])->toArray(),
            ])->toArray(),
            'programs' => Program::orderBy('id')->get(['id', 'code', 'name', 'department'])->toArray(),
            'curriculum_modules' => CurriculumModule::orderBy('id')->get(['id', 'curriculum_subject_id', 'title', 'target_completed_meetings', 'sort_order'])->toArray(),
            'instructors' => Instructor::with(['skills' => fn ($q) => $q->orderBy('skill')])->orderBy('id')->get()->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'user_id' => $i->user_id,
                'department' => $i->department,
                'status' => $i->status,
                'skills' => $i->skills->map(fn ($s) => ['skill' => $s->skill, 'level' => $s->level, 'competency_id' => $s->competency_id])->toArray(),
                'availabilities_count' => $i->availabilities()->count(),
            ])->toArray(),
            'course_classes' => CourseClass::with(['curriculumSnapshot', 'recurringPattern'])->orderBy('id')->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'student_count' => $c->student_count,
                'start_date' => (string) $c->start_date,
                'end_date' => (string) $c->end_date,
                'snapshot_payload' => $c->curriculumSnapshot?->snapshot_payload,
                'recurring_weekdays' => $c->recurringPattern?->preferred_weekdays,
                'recurring_start_time' => $c->recurringPattern?->start_time,
            ])->toArray(),
            'schedules' => Schedule::orderBy('id')->get(['id', 'course_class_id', 'meeting_sequence', 'curriculum_subject_id', 'instructor_id', 'room_id', 'date', 'start_time', 'end_time', 'status'])->map(fn ($s) => [
                'id' => $s->id,
                'course_class_id' => $s->course_class_id,
                'meeting_sequence' => $s->meeting_sequence,
                'curriculum_subject_id' => $s->curriculum_subject_id,
                'instructor_id' => $s->instructor_id,
                'room_id' => $s->room_id,
                'date' => $s->date instanceof DateTimeInterface ? $s->date->format('Y-m-d') : (string) $s->date,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'status' => $s->status,
            ])->toArray(),
            'proposals' => ScheduleProposal::with(['options', 'decisions'])->orderBy('id')->get()->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'status' => $p->status,
                'selected_option_id' => $p->selected_option_id,
                'options_count' => $p->options->count(),
                'decisions' => $p->decisions->map(fn ($d) => ['round' => $d->round, 'decision' => $d->decision, 'idempotency_token' => $d->idempotency_token])->toArray(),
            ])->toArray(),
        ];
    };

    $initialState = $captureDeepState();

    // Jalankan seeder putaran ke-2 (re-run langsung tanpa truncate/refresh)
    $this->seed(PalcomDemoSeeder::class);

    $secondState = $captureDeepState();

    // Verifikasi 1: Jumlah baris seluruh 22 tabel harus persis sama
    expect($secondState['counts'])->toEqual($initialState['counts']);

    // Verifikasi 2: Snapshot data mendalam, foreign key, dan relasi harus 100% identik tanpa mutasi tak terduga
    expect($secondState['users'])->toEqual($initialState['users'])
        ->and($secondState['rooms'])->toEqual($initialState['rooms'])
        ->and($secondState['programs'])->toEqual($initialState['programs'])
        ->and($secondState['curriculum_modules'])->toEqual($initialState['curriculum_modules'])
        ->and($secondState['instructors'])->toEqual($initialState['instructors'])
        ->and($secondState['course_classes'])->toEqual($initialState['course_classes'])
        ->and($secondState['schedules'])->toEqual($initialState['schedules'])
        ->and($secondState['proposals'])->toEqual($initialState['proposals']);
});

test('seeder aborts immediately in production environment', function () {
    app()['env'] = 'production';

    expect(fn () => (new PalcomDemoSeeder)->run())
        ->toThrow(RuntimeException::class, 'PalcomDemoSeeder dilarang keras dijalankan pada lingkungan produksi!');
});

test('selected option referential integrity holds across seeded proposals', function () {
    $this->seed(PalcomDemoSeeder::class);

    $proposals = ScheduleProposal::whereNotNull('selected_option_id')->get();
    expect($proposals)->not->toBeEmpty();

    foreach ($proposals as $proposal) {
        expect($proposal->selectedOption)->not->toBeNull()
            ->and($proposal->selectedOption->proposal_id)->toBe($proposal->id);
    }
});
