<?php

use App\Models\ActivityLog;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Services\Scheduling\SchedulingEngine;
use Database\Seeders\DemoScenarioSeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    // Siapkan data dasar master
    $this->engine = app(SchedulingEngine::class);
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
        ->and($fallbackLog->metadata['fallback_used'])->toBeTrue();
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

test('perintah demo diblokir secara ketat pada lingkungan production', function () {
    // Simulasikan environment production
    App::detectEnvironment(fn () => 'production');

    $exitCode = Artisan::call('scheduler:demo-data');
    expect($exitCode)->toBe(1); // Gagal dengan peringatan keamanan
});

test('perintah demo dapat dijalankan di production khusus showcase jika ALLOW_DEMO_DATA=true dan flag --force-demo diberikan', function () {
    App::detectEnvironment(fn () => 'production');
    putenv('ALLOW_DEMO_DATA=true');
    $_ENV['ALLOW_DEMO_DATA'] = 'true';

    try {
        $exitCode = Artisan::call('scheduler:demo-data', ['--force-demo' => true]);
        expect($exitCode)->toBe(0);

        $demoLeavesCount = InstructorLeave::where('reason', 'like', '%'.DemoScenarioSeeder::DEMO_PREFIX.'%')->count();
        expect($demoLeavesCount)->toBeGreaterThanOrEqual(1);
    } finally {
        putenv('ALLOW_DEMO_DATA');
        unset($_ENV['ALLOW_DEMO_DATA']);
    }
});
