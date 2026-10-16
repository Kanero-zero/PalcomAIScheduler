<?php

use App\Models\Competency;
use App\Models\CurriculumPeriod;
use App\Models\CurriculumSubject;
use App\Models\CurriculumVersion;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorSkill;
use App\Models\Program;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleApprovalDecision;
use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\User;
use App\Services\Scheduling\ProposalApprovalConflictValidator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('two competing proposals targeting the same slot cannot produce double-booking', function () {
    $validator = app(ProposalApprovalConflictValidator::class);

    // 1. Setup Master Data
    $baakUser = User::factory()->create(['role' => 'baak', 'email' => 'baak.concurrency@palcomtech.ac.id']);
    $aoUser = User::factory()->create(['role' => 'ao', 'email' => 'ao.concurrency@palcomtech.ac.id']);

    $room = Room::factory()->create([
        'name' => 'Lab Komputer 1',
        'department' => 'computer',
        'capacity' => 30,
        'status' => 'available',
    ]);

    $competency = Competency::create([
        'name' => 'Web Development',
        'department' => 'computer',
        'is_active' => true,
    ]);

    $program = Program::create([
        'code' => 'PROG-TEST-CC',
        'name' => 'Program Concurrency Test',
        'department' => 'computer',
        'is_active' => true,
    ]);

    $version = CurriculumVersion::create([
        'program_id' => $program->id,
        'version_code' => '2026.1',
        'status' => 'published',
    ]);

    $period = CurriculumPeriod::create([
        'curriculum_version_id' => $version->id,
        'name' => 'Periode 1',
        'sort_order' => 1,
        'duration_weeks' => 12,
    ]);

    $subject = CurriculumSubject::create([
        'curriculum_period_id' => $period->id,
        'competency_id' => $competency->id,
        'name' => 'Web Programming Dasar',
        'sessions_per_week' => 2,
        'sort_order' => 1,
    ]);

    $instructor = Instructor::factory()->create([
        'name' => 'Instruktur Utama',
        'department' => 'computer',
        'status' => 'active',
    ]);

    InstructorSkill::create([
        'instructor_id' => $instructor->id,
        'competency_id' => $competency->id,
        'skill' => 'Web Development',
        'level' => 'advanced',
    ]);

    // Ketersediaan mengajar: Senin jam 08:00 - 12:00
    InstructorAvailability::create([
        'instructor_id' => $instructor->id,
        'day' => 'Senin',
        'start_time' => '08:00',
        'end_time' => '12:00',
    ]);

    // 2. Proposal 1: Meminta Lab Komputer 1 pada Senin, 2026-11-23 jam 09:00 - 11:00
    $proposal1 = ScheduleProposal::create([
        'requested_by' => $aoUser->id,
        'title' => 'Proposal Kelas Web Pagi A (Pemenang Pertama)',
        'input_mode' => 'ai_assistant',
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 20,
        'start_date' => '2026-11-23',
        'end_date' => '2027-02-15',
        'total_meetings' => 24,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 2,
        'status' => 'pending',
    ]);

    $option1 = ScheduleProposalOption::create([
        'proposal_id' => $proposal1->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            [
                'date' => '2026-11-23', // Senin
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'room_id' => $room->id,
                'instructor_id' => $instructor->id,
                'curriculum_subject_id' => $subject->id,
            ],
        ],
        'conflicts_count' => 0,
        'score' => 95,
    ]);

    $proposal1->selectOption($option1);

    // 3. Proposal 2: Mengajukan slot yang PERSIS SAMA (Lab Komputer 1, 2026-11-23 09:00 - 11:00)
    $proposal2 = ScheduleProposal::create([
        'requested_by' => $aoUser->id,
        'title' => 'Proposal Kelas Web Pagi B (Pesaing Slot Sama)',
        'input_mode' => 'manual_input',
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 18,
        'start_date' => '2026-11-23',
        'end_date' => '2027-02-15',
        'total_meetings' => 24,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 2,
        'status' => 'pending',
    ]);

    $option2 = ScheduleProposalOption::create([
        'proposal_id' => $proposal2->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            [
                'date' => '2026-11-23', // Senin - Jam dan Ruang Sama
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'room_id' => $room->id,
                'instructor_id' => $instructor->id,
                'curriculum_subject_id' => $subject->id,
            ],
        ],
        'conflicts_count' => 0,
        'score' => 90,
    ]);

    $proposal2->selectOption($option2);

    // SEBELUM APPROVAL: Kedua proposal berstatus pending dan belum ada row schedules sama sekali
    expect(Schedule::count())->toBe(0)
        ->and($proposal1->fresh()->status)->toBe('pending')
        ->and($proposal2->fresh()->status)->toBe('pending');

    // 4. BAAK menyetujui Proposal 1 melalui transaksi & concurrency lock
    $decision1 = $validator->executeApprovalWithLock(
        proposal: $proposal1,
        option: $option1,
        baakUser: $baakUser,
        idempotencyToken: 'idemp-token-prop-1-approval',
        reason: 'Disetujui: Slot ruangan dan instruktur valid dan aman.'
    );

    // Verifikasi Proposal 1 sukses approved dan membuat tepat 1 jadwal resmi
    expect($decision1)->toBeInstanceOf(ScheduleApprovalDecision::class)
        ->and($decision1->decision)->toBe('approved')
        ->and($proposal1->fresh()->status)->toBe('approved')
        ->and(Schedule::count())->toBe(1);

    $officialSchedule = Schedule::first();
    expect($officialSchedule->room_id)->toBe($room->id)
        ->and($officialSchedule->instructor_id)->toBe($instructor->id)
        ->and($officialSchedule->date->format('Y-m-d'))->toBe('2026-11-23')
        ->and($officialSchedule->start_time)->toBe('09:00:00')
        ->and($officialSchedule->end_time)->toBe('11:00:00')
        ->and($officialSchedule->status)->toBe('scheduled');

    // 5. BAAK atau proses lain mencoba menyetujui Proposal 2 yang meminta slot yang sama
    // Sistem WAJIB menolak (fail-closed) dengan ValidationException dan TIDAK BOLEH membuat double-booking
    $exceptionThrown = false;
    try {
        $validator->executeApprovalWithLock(
            proposal: $proposal2,
            option: $option2,
            baakUser: $baakUser,
            idempotencyToken: 'idemp-token-prop-2-approval',
            reason: 'Mencoba menyetujui proposal kedua pada slot yang sudah terisi.'
        );
    } catch (ValidationException $e) {
        $exceptionThrown = true;
        expect($e->getMessage())->toContain('ditemukan bentrok terkini');
    }

    expect($exceptionThrown)->toBeTrue();

    // 6. Verifikasi Integritas Mutlak: Zero Double-Booking
    // - Proposal 2 tetap berstatus 'pending' (tidak berubah menjadi approved)
    // - Tidak ada keputusan approved untuk Proposal 2
    // - Jumlah jadwal resmi di database TETAP 1 (sama sekali tidak ada jadwal ganda)
    expect($proposal2->fresh()->status)->toBe('pending')
        ->and($proposal2->fresh()->decisions)->toHaveCount(0)
        ->and(Schedule::count())->toBe(1)
        ->and(Schedule::where('room_id', $room->id)->whereDate('date', '2026-11-23')->count())->toBe(1);
});

test('idempotency token prevents duplicate approval decisions and schedules on repeated requests', function () {
    $validator = app(ProposalApprovalConflictValidator::class);

    $baakUser = User::factory()->create(['role' => 'baak']);
    $aoUser = User::factory()->create(['role' => 'ao']);
    $room = Room::factory()->create(['capacity' => 25, 'status' => 'available']);
    $program = Program::create(['code' => 'PROG-TEST-IDEMP', 'name' => 'Program Idempotency', 'department' => 'computer', 'is_active' => true]);
    $version = CurriculumVersion::create(['program_id' => $program->id, 'version_code' => '2026.1', 'status' => 'published']);

    $proposal = ScheduleProposal::create([
        'requested_by' => $aoUser->id,
        'title' => 'Proposal Idempotency Verification',
        'input_mode' => 'ai_assistant',
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 15,
        'start_date' => '2026-11-25',
        'end_date' => '2027-02-15',
        'total_meetings' => 12,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 1,
        'status' => 'pending',
    ]);

    $instructor = Instructor::factory()->create(['department' => 'computer', 'status' => 'active']);
    $option = ScheduleProposalOption::create([
        'proposal_id' => $proposal->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            [
                'date' => '2026-11-25',
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'room_id' => $room->id,
                'instructor_id' => $instructor->id,
            ],
        ],
        'conflicts_count' => 0,
        'score' => 95,
    ]);

    $proposal->selectOption($option);

    $sharedToken = 'unique-idempotency-token-12345';

    // Eksekusi pertama
    $decision1 = $validator->executeApprovalWithLock($proposal, $option, $baakUser, $sharedToken, 'Approval pertama');
    expect(ScheduleApprovalDecision::count())->toBe(1)
        ->and(Schedule::count())->toBe(1)
        ->and($proposal->fresh()->status)->toBe('approved');

    // Eksekusi kedua dengan token yang sama (misal network retry / double click)
    $decision2 = $validator->executeApprovalWithLock($proposal, $option, $baakUser, $sharedToken, 'Approval kedua ulang');

    // Harus mengembalikan record keputusan yang sama tanpa membuat duplikasi jadwal atau keputusan
    expect($decision2->id)->toBe($decision1->id)
        ->and(ScheduleApprovalDecision::count())->toBe(1)
        ->and(Schedule::count())->toBe(1);
});

test('two competing proposals targeting the same instructor in different rooms cannot produce double-booking', function () {
    $validator = app(ProposalApprovalConflictValidator::class);

    $baakUser = User::factory()->create(['role' => 'baak']);
    $aoUser = User::factory()->create(['role' => 'ao']);

    $roomA = Room::factory()->create(['name' => 'Ruang Teori A', 'department' => 'speakout', 'capacity' => 20, 'status' => 'available']);
    $roomB = Room::factory()->create(['name' => 'Ruang Teori B', 'department' => 'speakout', 'capacity' => 20, 'status' => 'available']);

    $instructor = Instructor::factory()->create([
        'name' => 'Instruktur Berbagi',
        'department' => 'speakout',
        'status' => 'active',
    ]);

    // Ketersediaan: Selasa 13:00 - 17:00
    InstructorAvailability::create([
        'instructor_id' => $instructor->id,
        'day' => 'Selasa',
        'start_time' => '13:00',
        'end_time' => '17:00',
    ]);

    // Proposal X: Instruktur Berbagi di Ruang A pada Selasa 2026-11-24 14:00 - 16:00
    $proposalX = ScheduleProposal::create([
        'requested_by' => $aoUser->id,
        'title' => 'Proposal Speakout X',
        'input_mode' => 'manual_input',
        'department' => 'speakout',
        'class_kind' => 'regular',
        'student_count' => 10,
        'start_date' => '2026-11-24',
        'end_date' => '2027-01-20',
        'total_meetings' => 12,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 1,
        'status' => 'pending',
    ]);

    $optionX = ScheduleProposalOption::create([
        'proposal_id' => $proposalX->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            [
                'date' => '2026-11-24',
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'room_id' => $roomA->id,
                'instructor_id' => $instructor->id,
            ],
        ],
        'conflicts_count' => 0,
        'score' => 95,
    ]);

    $proposalX->selectOption($optionX);

    // Proposal Y: Instruktur Berbagi di Ruang B pada Selasa 2026-11-24 14:00 - 16:00 (Jam Sama, Instruktur Sama, Ruang Beda)
    $proposalY = ScheduleProposal::create([
        'requested_by' => $aoUser->id,
        'title' => 'Proposal Speakout Y',
        'input_mode' => 'manual_input',
        'department' => 'speakout',
        'class_kind' => 'regular',
        'student_count' => 12,
        'start_date' => '2026-11-24',
        'end_date' => '2027-01-20',
        'total_meetings' => 12,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 1,
        'status' => 'pending',
    ]);

    $optionY = ScheduleProposalOption::create([
        'proposal_id' => $proposalY->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => [
            [
                'date' => '2026-11-24',
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'room_id' => $roomB->id,
                'instructor_id' => $instructor->id,
            ],
        ],
        'conflicts_count' => 0,
        'score' => 90,
    ]);

    $proposalY->selectOption($optionY);

    // 1. Setujui Proposal X
    $validator->executeApprovalWithLock($proposalX, $optionX, $baakUser, 'token-x-approval');
    expect($proposalX->fresh()->status)->toBe('approved')
        ->and(Schedule::count())->toBe(1);

    // 2. Coba setujui Proposal Y -> Harus Gagal karena Instruktur Berbagi sudah terjadwal di Proposal X
    $thrown = false;
    try {
        $validator->executeApprovalWithLock($proposalY, $optionY, $baakUser, 'token-y-approval');
    } catch (ValidationException $e) {
        $thrown = true;
        expect($e->getMessage())->toContain('Instruktur telah dijadwalkan mengajar pada kelas lain');
    }

    expect($thrown)->toBeTrue()
        ->and($proposalY->fresh()->status)->toBe('pending')
        ->and(Schedule::count())->toBe(1)
        ->and(Schedule::where('instructor_id', $instructor->id)->count())->toBe(1);
});

test('two independent database connections truly competing concurrently are mutually excluded and prevented from double-booking', function () {
    // 1. Siapkan database SQLite fisik terpisah untuk simulasi dua koneksi PDO nyata
    $tempDbPath = storage_path('framework/testing/test_concurrency_'.uniqid().'.sqlite');
    @mkdir(dirname($tempDbPath), 0777, true);
    touch($tempDbPath);

    Config::set('database.connections.conn_alpha', [
        'driver' => 'sqlite',
        'database' => $tempDbPath,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 100, // 100ms timeout untuk menangkap SQLITE_BUSY jika terkunci
    ]);
    Config::set('database.connections.conn_beta', [
        'driver' => 'sqlite',
        'database' => $tempDbPath,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 100,
    ]);

    try {
        Artisan::call('migrate', ['--database' => 'conn_alpha']);

        $validator = app(ProposalApprovalConflictValidator::class);

        // 2. Setup master data melalui koneksi alpha
        $connAlpha = DB::connection('conn_alpha');
        $connBeta = DB::connection('conn_beta');

        $baakId = $connAlpha->table('users')->insertGetId([
            'name' => 'BAAK Concurrency User',
            'email' => 'baak.multi.conn@palcomtech.ac.id',
            'password' => bcrypt('secret'),
            'role' => 'baak',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $aoId = $connAlpha->table('users')->insertGetId([
            'name' => 'AO Concurrency User',
            'email' => 'ao.multi.conn@palcomtech.ac.id',
            'password' => bcrypt('secret'),
            'role' => 'ao',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomId = $connAlpha->table('rooms')->insertGetId([
            'name' => 'Lab Komputer Concurrency',
            'department' => 'computer',
            'capacity' => 25,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $competencyId = $connAlpha->table('competencies')->insertGetId([
            'name' => 'Concurrency Programming',
            'department' => 'computer',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $instructorId = $connAlpha->table('instructors')->insertGetId([
            'user_id' => null,
            'name' => 'Instruktur Concurrency',
            'department' => 'computer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connAlpha->table('instructor_skills')->insert([
            'instructor_id' => $instructorId,
            'competency_id' => $competencyId,
            'skill' => 'Concurrency Programming',
            'level' => 'advanced',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connAlpha->table('instructor_availabilities')->insert([
            'instructor_id' => $instructorId,
            'day' => 'Senin',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = $connAlpha->table('course_classes')->insertGetId([
            'name' => 'Kelas Concurrency',
            'subject' => 'Concurrency Programming',
            'student_count' => 20,
            'status' => 'active',
            'department' => 'computer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dua proposal berbeda yang meminta slot waktu yang sama (2026-11-23 09:00 - 11:00)
        $prop1Id = $connAlpha->table('schedule_proposals')->insertGetId([
            'requested_by' => $aoId,
            'course_class_id' => $classId,
            'title' => 'Proposal 1 Koneksi Alpha',
            'input_mode' => 'ai_assistant',
            'department' => 'computer',
            'class_kind' => 'regular',
            'student_count' => 20,
            'start_date' => '2026-11-23',
            'end_date' => '2027-02-15',
            'total_meetings' => 24,
            'session_duration_minutes' => 120,
            'frequency_per_week' => 2,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $opt1Id = $connAlpha->table('schedule_proposal_options')->insertGetId([
            'proposal_id' => $prop1Id,
            'option_label' => 'A',
            'proposed_schedules_payload' => json_encode([
                [
                    'date' => '2026-11-23',
                    'start_time' => '09:00:00',
                    'end_time' => '11:00:00',
                    'room_id' => $roomId,
                    'instructor_id' => $instructorId,
                ],
            ]),
            'conflicts_count' => 0,
            'score' => 95,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prop2Id = $connAlpha->table('schedule_proposals')->insertGetId([
            'requested_by' => $aoId,
            'course_class_id' => $classId,
            'title' => 'Proposal 2 Koneksi Beta',
            'input_mode' => 'manual_input',
            'department' => 'computer',
            'class_kind' => 'regular',
            'student_count' => 18,
            'start_date' => '2026-11-23',
            'end_date' => '2027-02-15',
            'total_meetings' => 24,
            'session_duration_minutes' => 120,
            'frequency_per_week' => 2,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $opt2Id = $connAlpha->table('schedule_proposal_options')->insertGetId([
            'proposal_id' => $prop2Id,
            'option_label' => 'A',
            'proposed_schedules_payload' => json_encode([
                [
                    'date' => '2026-11-23',
                    'start_time' => '09:00:00',
                    'end_time' => '11:00:00',
                    'room_id' => $roomId,
                    'instructor_id' => $instructorId,
                ],
            ]),
            'conflicts_count' => 0,
            'score' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // TAHAP 1: Pembuktian Isolasi Database Saat Transaksi Berjalan (In-Flight Concurrency)
        // Koneksi Alpha memulai transaksi approval dan mengunci resource
        $connAlpha->beginTransaction();
        $connAlpha->table('schedule_approval_decisions')->insert([
            'proposal_id' => $prop1Id,
            'round' => 1,
            'decision_by' => $baakId,
            'decision' => 'approved',
            'reason' => 'Alpha approving in-flight',
            'idempotency_token' => 'token-alpha-inflight',
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $connAlpha->table('schedules')->insert([
            'course_class_id' => $classId,
            'meeting_sequence' => 1,
            'instructor_id' => $instructorId,
            'room_id' => $roomId,
            'date' => '2026-11-23 00:00:00',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $connAlpha->table('schedule_proposals')->where('id', $prop1Id)->update([
            'selected_option_id' => $opt1Id,
            'status' => 'approved',
        ]);

        // Saat transaksi Alpha masih TERBUKA, Koneksi Beta (proses independen lain) mencoba menulis/memproses approval
        $betaLockedOut = false;
        try {
            $connBeta->table('schedule_approval_decisions')->insert([
                'proposal_id' => $prop2Id,
                'round' => 1,
                'decision_by' => $baakId,
                'decision' => 'approved',
                'reason' => 'Beta trying to write simultaneously',
                'idempotency_token' => 'token-beta-simultaneous',
                'decided_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $betaLockedOut = true;
            // SQLite melempar error "database is locked" karena Alpha sedang memegang write lock fisik
            expect($e->getMessage())->toContain('database is locked');
        }
        expect($betaLockedOut)->toBeTrue();

        // TAHAP 2: Koneksi Alpha Commit secara sah
        $connAlpha->commit();

        // TAHAP 3: Koneksi Beta sekarang mencoba approval setelah lock Alpha dilepas.
        // Koneksi Beta sekarang dapat mengakses database, tetapi query validasi in-transaction
        // membaca jadwal resmi yang baru saja ditulis oleh Alpha, sehingga menolak Proposal 2 (Fail-Closed).
        Config::set('database.default', 'conn_beta');
        $proposal2Beta = ScheduleProposal::on('conn_beta')->findOrFail($prop2Id);
        $option2Beta = ScheduleProposalOption::on('conn_beta')->findOrFail($opt2Id);
        $baakUserBeta = User::on('conn_beta')->findOrFail($baakId);

        $betaValidationFailed = false;
        try {
            $validator->executeApprovalWithLock(
                proposal: $proposal2Beta,
                option: $option2Beta,
                baakUser: $baakUserBeta,
                idempotencyToken: 'token-beta-post-commit'
            );
        } catch (ValidationException $e) {
            $betaValidationFailed = true;
            expect($e->getMessage())->toContain('Ruangan telah terisi oleh jadwal resmi lain')
                ->and($e->errors())->toHaveKey('approval');
        }

        expect($betaValidationFailed)->toBeTrue();

        // Verifikasi Akhir: Jumlah jadwal resmi tetap 1 (ZERO double-booking)
        expect($connBeta->table('schedules')->count())->toBe(1)
            ->and($connBeta->table('schedule_proposals')->where('id', $prop1Id)->value('status'))->toBe('approved')
            ->and($connBeta->table('schedule_proposals')->where('id', $prop2Id)->value('status'))->toBe('pending')
            ->and($connBeta->table('schedule_approval_decisions')->count())->toBe(1);

    } finally {
        Config::set('database.default', 'sqlite');
        DB::disconnect('conn_alpha');
        DB::disconnect('conn_beta');
        @unlink($tempDbPath);
    }
});
