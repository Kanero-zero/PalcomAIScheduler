<?php

use App\Models\ActivityLog;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use Database\Seeders\DemoScenarioSeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Siapkan data dasar master
    $this->engine = app(SchedulingEngine::class);
});

afterEach(function () {
    App::detectEnvironment(fn () => 'testing');
    config(['database.default' => 'sqlite']);
    DB::setDefaultConnection('sqlite');
    DB::purge('demo_isolated');
});

test('perintah scheduler:demo-data menyiapkan seluruh 9 skenario demonstrasi secara lengkap', function () {
    $exitCode = Artisan::call('scheduler:demo-data');
    expect($exitCode)->toBe(0);

    // SKENARIO 1 & 2: Izin Wahyu dengan beberapa kelas terdampak & Kanero sebagai kandidat valid
    $leaveWahyu = InstructorLeave::where('reason', 'like', '%Urusan Dinas Kampus%')->firstOrFail();
    $evalWahyu = $this->engine->evaluateLeave($leaveWahyu);

    expect($evalWahyu->totalAffectedSchedules)->toBe(2)
        ->and($evalWahyu->allSchedulesResolved)->toBeTrue();

    // Verifikasi Skenario 2: Kanero lolos sebagai kandidat valid di kedua kelas
    foreach ($evalWahyu->affectedSchedules as $schedEval) {
        $validInstructorIds = collect($schedEval->validCandidates)->pluck('instructorId')->all();
        expect($validInstructorIds)->toContain(2); // Kanero
    }

    // Verifikasi Skenario 3: Dina Oktavia didiskualifikasi karena tidak memiliki kompetensi
    foreach ($evalWahyu->affectedSchedules as $schedEval) {
        $disqualifiedDina = collect($schedEval->disqualifiedCandidates)->firstWhere('instructorName', 'Dina Oktavia');
        expect($disqualifiedDina)->not->toBeNull()
            ->and(implode(' ', $disqualifiedDina->reasons))->toContain('kompetensi');
    }

    // Verifikasi Skenario 4: Budi Santoso didiskualifikasi pada sesi 13:00 - 15:00 karena bentrok jadwal
    $schedExcel = collect($evalWahyu->affectedSchedules)->firstWhere('subject', 'Microsoft Excel');
    $disqualifiedBudi = collect($schedExcel->disqualifiedCandidates)->firstWhere('instructorName', 'Budi Santoso');
    expect($disqualifiedBudi)->not->toBeNull()
        ->and(strtolower(implode(' ', $disqualifiedBudi->reasons)))->toContain('bentrok');

    // SKENARIO 5: Kendala Ruangan (Kapasitas tidak cukup atau status maintenance)
    $leaveRoom = InstructorLeave::where('reason', 'like', '%Kendala Ruangan%')->firstOrFail();
    $evalRoom = $this->engine->evaluateLeave($leaveRoom);
    expect($evalRoom->totalAffectedSchedules)->toBe(1);

    $roomEval = $evalRoom->affectedSchedules[0]->roomEvaluation;
    expect($roomEval)->not->toBeNull()
        ->and($roomEval->isStatusAvailable)->toBeFalse(); // status maintenance

    // SKENARIO 6: Izin tanpa kelas terdampak
    $leaveNoAffected = InstructorLeave::where('reason', 'like', '%Tanpa Kelas Terdampak%')->firstOrFail();
    $evalNoAffected = $this->engine->evaluateLeave($leaveNoAffected);
    expect($evalNoAffected->totalAffectedSchedules)->toBe(0);

    // SKENARIO 7: Approval yang berhasil tercatat di substitusi dan log
    $approvedSub = ScheduleSubstitution::where('status', 'approved')
        ->where('notes', 'like', '%'.DemoScenarioSeeder::DEMO_PREFIX.'%')
        ->firstOrFail();

    expect($approvedSub->replacement_instructor_id)->toBe(2); // Kanero

    $approvedLog = ActivityLog::where('type', 'approval')
        ->where('state', 'success')
        ->where('instructor_leave_id', $approvedSub->instructor_leave_id)
        ->first();
    expect($approvedLog)->not->toBeNull();

    // SKENARIO 8: Reject beserta alasannya tercatat di substitusi dan log
    $rejectedSub = ScheduleSubstitution::where('status', 'rejected')
        ->where('rejection_reason', 'like', '%'.DemoScenarioSeeder::DEMO_PREFIX.'%')
        ->firstOrFail();

    expect($rejectedSub->rejection_reason)->toContain('beban mengajar');

    $rejectedLog = ActivityLog::where('type', 'approval')
        ->where('state', 'rejected')
        ->where('instructor_leave_id', $rejectedSub->instructor_leave_id)
        ->first();
    expect($rejectedLog)->not->toBeNull();

    // SKENARIO 9: Skenario Gemini Fallback tercatat di Activity Log
    $fallbackLog = ActivityLog::where('type', 'ai')
        ->where('state', 'fallback')
        ->first();

    expect($fallbackLog)->not->toBeNull()
        ->and($fallbackLog->state_label)->toBe('Mode Fallback')
        ->and($fallbackLog->metadata['fallback_used'])->toBeTrue()
        ->and($fallbackLog->metadata['simulation'])->toBeTrue();
});

test('perintah seeder demo bersifat idempotent dan aman dijalankan berulang kali', function () {
    // Jalankan pertama kali
    Artisan::call('scheduler:demo-data');
    $leavesCount1 = InstructorLeave::where('reason', 'like', '%'.DemoScenarioSeeder::DEMO_PREFIX.'%')->count();
    $schedulesCount1 = Schedule::whereDate('date', DemoScenarioSeeder::DEMO_DATE)->count();

    // Jalankan kedua kali
    Artisan::call('scheduler:demo-data');
    $leavesCount2 = InstructorLeave::where('reason', 'like', '%'.DemoScenarioSeeder::DEMO_PREFIX.'%')->count();
    $schedulesCount2 = Schedule::whereDate('date', DemoScenarioSeeder::DEMO_DATE)->count();

    // Pastikan jumlah data identik dan tidak terjadi duplikasi berulang
    expect($leavesCount1)->toBe($leavesCount2)
        ->and($schedulesCount1)->toBe($schedulesCount2);
});

test('opsi clean membersihkan seluruh data demo secara aman tanpa merusak data pengguna lain', function () {
    // 1. Buat data pengguna nyata independen
    $realInstructor = Instructor::create(['name' => 'Instruktur Operasional Nyata', 'status' => 'active']);
    $realClass = CourseClass::create(['name' => 'Kelas Reguler Tetap', 'subject' => 'Akuntansi', 'student_count' => 15]);
    $realRoom = Room::create(['name' => 'Ruang 101 Tetap', 'capacity' => 20, 'status' => 'available']);
    $realSchedule = Schedule::create([
        'course_class_id' => $realClass->id,
        'instructor_id' => $realInstructor->id,
        'room_id' => $realRoom->id,
        'date' => '2026-11-01',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => 'scheduled',
    ]);
    $realLeave = InstructorLeave::create([
        'instructor_id' => $realInstructor->id,
        'date' => '2026-11-01',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'reason' => 'Izin Operasional Nyata',
        'status' => 'pending',
    ]);

    // 2. Siapkan data demo
    Artisan::call('scheduler:demo-data');

    // 3. Bersihkan data demo dengan flag --clean
    $cleanExitCode = Artisan::call('scheduler:demo-data', ['--clean' => true]);
    expect($cleanExitCode)->toBe(0);

    // 4. Pastikan data demo terhapus
    $demoLeavesCount = InstructorLeave::where('reason', 'like', '%'.DemoScenarioSeeder::DEMO_PREFIX.'%')->count();
    expect($demoLeavesCount)->toBe(0);

    // 5. Pastikan data operasional pengguna TETAP UTUH
    expect(Instructor::find($realInstructor->id))->not->toBeNull()
        ->and(CourseClass::find($realClass->id))->not->toBeNull()
        ->and(Room::find($realRoom->id))->not->toBeNull()
        ->and(Schedule::find($realSchedule->id))->not->toBeNull()
        ->and(InstructorLeave::find($realLeave->id))->not->toBeNull();
});

test('perintah demo diblokir secara ketat pada lingkungan production jika DEMO_MODE tidak aktif', function () {
    try {
        App::detectEnvironment(fn () => 'production');
        putenv('DEMO_MODE=false');
        $_ENV['DEMO_MODE'] = 'false';

        $exitCode = Artisan::call('scheduler:demo-data');
        expect($exitCode)->toBe(1); // Gagal dengan peringatan keamanan
    } finally {
        App::detectEnvironment(fn () => 'testing');
        putenv('DEMO_MODE');
        unset($_ENV['DEMO_MODE']);
    }
});

test('perintah demo dapat dijalankan di production jika DEMO_MODE=true pada database terisolasi', function () {
    putenv('DEMO_MODE=true');
    $_ENV['DEMO_MODE'] = 'true';

    try {
        App::detectEnvironment(fn () => 'production');
        $exitCode = Artisan::call('scheduler:demo-data');
        expect($exitCode)->toBe(0);

        $demoLeavesCount = InstructorLeave::on('demo')->where('reason', 'like', '%'.DemoScenarioSeeder::DEMO_PREFIX.'%')->count();
        expect($demoLeavesCount)->toBeGreaterThanOrEqual(1);
    } finally {
        App::detectEnvironment(fn () => 'testing');
        putenv('DEMO_MODE');
        unset($_ENV['DEMO_MODE']);
        $demoDb = config('database.connections.demo.database', database_path('demo.sqlite'));
        if (file_exists($demoDb)) {
            @unlink($demoDb);
        }
    }
});

test('skenario gemini fallback yang benar-benar dieksekusi saat api gagal menghasilkan fallback status valid', function () {
    Artisan::call('scheduler:demo-data');
    $leave = InstructorLeave::where('reason', 'like', '%'.DemoScenarioSeeder::DEMO_PREFIX.'%')->firstOrFail();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Simulasi Layanan Gemini Down'], 503),
    ]);

    $result = $this->engine->evaluateWithAi($leave);

    expect($result->aiSummary['status'])->toBe('fallback')
        ->and($result->aiSummary['fallback_used'])->toBeTrue()
        ->and($result->allSchedulesResolved)->toBeTrue();

    // Pastikan log fallback hasil eksekusi nyata tercatat
    $realFallbackLog = ActivityLog::where('type', 'ai')
        ->where('state', 'fallback')
        ->where('instructor_leave_id', $leave->id)
        ->latest('id')
        ->first();

    expect($realFallbackLog)->not->toBeNull()
        ->and($realFallbackLog->metadata['fallback_used'])->toBeTrue();
});

test('opsi clean tidak menghapus jadwal dan izin operasional asli pada tanggal demo', function () {
    $realInstructor = Instructor::create(['name' => 'Instruktur Tetap Kampus', 'status' => 'active']);
    $realClass = CourseClass::create(['name' => 'Kelas Asli Mahasiswa', 'subject' => 'Basis Data', 'student_count' => 20]);
    $realRoom = Room::create(['name' => 'Lab Komputer 5', 'capacity' => 25, 'status' => 'available']);

    // Buat jadwal dan izin operasional NYATA pada tanggal yang sama dengan demo (DemoScenarioSeeder::DEMO_DATE)
    $realScheduleSameDate = Schedule::create([
        'course_class_id' => $realClass->id,
        'instructor_id' => $realInstructor->id,
        'room_id' => $realRoom->id,
        'date' => DemoScenarioSeeder::DEMO_DATE,
        'start_time' => '07:00:00',
        'end_time' => '09:00:00',
        'status' => 'scheduled',
    ]);

    $realLeaveSameDate = InstructorLeave::create([
        'instructor_id' => $realInstructor->id,
        'date' => DemoScenarioSeeder::DEMO_DATE,
        'start_time' => '07:00:00',
        'end_time' => '09:00:00',
        'reason' => 'Izin Dinas Nyata Dosen',
        'status' => 'approved',
    ]);

    // Jalankan setup demo dan cleanup demo
    Artisan::call('scheduler:demo-data');
    Artisan::call('scheduler:demo-data', ['--clean' => true]);

    // Pastikan jadwal dan izin operasional pada tanggal demo TIDAK terhapus
    expect(Schedule::find($realScheduleSameDate->id))->not->toBeNull()
        ->and(InstructorLeave::find($realLeaveSameDate->id))->not->toBeNull();
});

test('seeder tidak menimpa password admin yang sudah ada dan tidak menggunakan password statis bawaan', function () {
    $customHash = bcrypt('SuperSecretAdminPassword123!');
    $admin = User::create([
        'name' => 'Admin Tetap BAAK',
        'email' => 'admin@palcomtech.ac.id',
        'password' => $customHash,
        'email_verified_at' => now(),
    ]);

    Artisan::call('scheduler:demo-data');

    $admin->refresh();
    expect($admin->password)->toBe($customHash);
});

test('seeder tidak mengubah kapasitas atau data master ruangan yang sudah ada', function () {
    $existingRoom = Room::create([
        'name' => 'Lab 1',
        'capacity' => 99,
        'status' => 'available',
    ]);

    Artisan::call('scheduler:demo-data');

    $existingRoom->refresh();
    expect($existingRoom->capacity)->toBe(99);
});

test('perintah demo dapat berjalan pada database terisolasi menggunakan opsi --isolated', function () {
    $isolatedDbPath = database_path('demo.sqlite');
    if (file_exists($isolatedDbPath)) {
        @unlink($isolatedDbPath);
    }

    try {
        $exitCode = Artisan::call('scheduler:demo-data', ['--isolated' => true]);
        expect($exitCode)->toBe(0)
            ->and(file_exists($isolatedDbPath))->toBeTrue();
    } finally {
        if (file_exists($isolatedDbPath)) {
            @unlink($isolatedDbPath);
        }
    }
});
