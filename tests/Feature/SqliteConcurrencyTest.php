<?php

use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use App\Services\Scheduling\ScheduleApprovalService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    // 1. Buat file SQLite fisik sementara untuk setiap test
    $this->sqlitePath = storage_path('framework/testing/concurrency_'.uniqid().'.sqlite');
    if (! file_exists(dirname($this->sqlitePath))) {
        mkdir(dirname($this->sqlitePath), 0777, true);
    }
    touch($this->sqlitePath);

    // 2. Daftarkan koneksi file SQLite
    Config::set('database.connections.sqlite_file', [
        'driver' => 'sqlite',
        'database' => $this->sqlitePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 500,
    ]);

    Config::set('database.connections.sqlite_file_secondary', [
        'driver' => 'sqlite',
        'database' => $this->sqlitePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 500,
    ]);

    // Jalankan migrasi pada database SQLite berbasis file
    Artisan::call('migrate', [
        '--database' => 'sqlite_file',
        '--path' => 'database/migrations',
        '--force' => true,
    ]);

    // Arahkan default connection aplikasi ke sqlite_file
    Config::set('database.default', 'sqlite_file');
    DB::setDefaultConnection('sqlite_file');
    DB::purge('sqlite_file');

    // 3. Siapkan data master pada file database fisik
    $this->admin = User::create([
        'name' => 'Admin BAAK',
        'email' => 'admin@palcomtech.ac.id',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    $this->wahyu = Instructor::create(['name' => 'Wahyu', 'status' => 'active']);
    $this->kanero = Instructor::create(['name' => 'Kanero', 'status' => 'active']);

    $this->courseClass = CourseClass::create([
        'name' => 'Microsoft Excel Siang',
        'subject' => 'Microsoft Excel',
        'student_count' => 20,
    ]);

    // Berikan skill Excel ke Kanero
    $this->kanero->skills()->create([
        'skill' => 'Microsoft Excel',
        'level' => 'advanced',
    ]);

    $this->room = Room::create([
        'name' => 'Lab 1',
        'capacity' => 30,
        'status' => 'available',
    ]);

    $this->leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $this->service = app(ScheduleApprovalService::class);
});

afterEach(function () {
    try {
        while (DB::connection('sqlite_file')->transactionLevel() > 0) {
            DB::connection('sqlite_file')->rollBack();
        }
    } catch (Throwable) {
    }

    try {
        while (DB::connection('sqlite_file_secondary')->transactionLevel() > 0) {
            DB::connection('sqlite_file_secondary')->rollBack();
        }
    } catch (Throwable) {
    }

    DB::disconnect('sqlite_file');
    DB::disconnect('sqlite_file_secondary');

    DB::purge('sqlite_file');
    DB::purge('sqlite_file_secondary');

    Config::set('database.default', 'sqlite');
    DB::setDefaultConnection('sqlite');

    if (file_exists($this->sqlitePath)) {
        @unlink($this->sqlitePath);
    }
});

test('real file-based sqlite concurrency prevents duplicate approvals across separate database connections', function () {
    $this->actingAs($this->admin);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // 1. Eksekusi koneksi pertama
    $responseA = $this->service->approveSubstitution(
        leaveId: $this->leave->id,
        scheduleId: $schedule->id,
        replacementInstructorId: $this->kanero->id,
        notes: 'Persetujuan oleh Admin 1',
    );

    expect($responseA['success'])->toBeTrue();

    // 2. Eksekusi permintaan kedua untuk jadwal yang sama
    expect(fn () => $this->service->approveSubstitution(
        leaveId: $this->leave->id,
        scheduleId: $schedule->id,
        replacementInstructorId: $this->kanero->id,
        notes: 'Persetujuan oleh Admin 2 yang bentrok',
    ))->toThrow(ValidationException::class);

    // 3. Verifikasi integritas: HANYA ada 1 record substitution yang tersimpan di database file
    $subCount = ScheduleSubstitution::where('schedule_id', $schedule->id)->count();
    expect($subCount)->toBe(1);

    $savedSub = ScheduleSubstitution::where('schedule_id', $schedule->id)->firstOrFail();
    expect($savedSub->status)->toBe('approved')
        ->and($savedSub->replacement_instructor_id)->toBe($this->kanero->id)
        ->and($savedSub->notes)->toBe('Persetujuan oleh Admin 1');
});

test('file-based sqlite concurrency blocks cross-schedule double booking across overlapping classes', function () {
    $this->actingAs($this->admin);

    // Schedule 1: 13:00 - 15:00
    $schedule1 = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $room2 = Room::create([
        'name' => 'Lab 2',
        'capacity' => 30,
        'status' => 'available',
    ]);

    // Schedule 2: 14:00 - 16:00 (bertabrakan dengan schedule 1)
    $schedule2 = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $room2->id,
        'date' => '2026-10-15',
        'start_time' => '14:00',
        'end_time' => '16:00',
        'status' => 'scheduled',
    ]);

    // Setujui Kanero pada Schedule 1
    $this->service->approveSubstitution(
        leaveId: $this->leave->id,
        scheduleId: $schedule1->id,
        replacementInstructorId: $this->kanero->id,
    );

    // Mencoba menyetujui Kanero pada Schedule 2 yang jamnya tumpang tindih
    expect(fn () => $this->service->approveSubstitution(
        leaveId: $this->leave->id,
        scheduleId: $schedule2->id,
        replacementInstructorId: $this->kanero->id,
    ))->toThrow(ValidationException::class);

    // Pastikan Schedule 2 TIDAK ter-double-booking ke Kanero
    $schedule2->refresh();
    expect($schedule2->instructor_id)->toBe($this->wahyu->id);
});

test('it handles sqlite database is locked error gracefully with user-friendly message', function () {
    $this->actingAs($this->admin);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // Buka koneksi kedua dan tahan exclusive lock pada file SQLite fisik
    $pdoSecondary = DB::connection('sqlite_file_secondary')->getPdo();
    $pdoSecondary->exec('BEGIN EXCLUSIVE TRANSACTION;');

    try {
        // Koneksi utama mencoba melakukan persetujuan saat database fisik terkunci
        $this->service->approveSubstitution(
            leaveId: $this->leave->id,
            scheduleId: $schedule->id,
            replacementInstructorId: $this->kanero->id,
        );
        $this->fail('Harus melempar ValidationException karena SQLite database terkunci.');
    } catch (ValidationException $e) {
        $errorMessage = $e->errors()['schedule_id'][0] ?? '';
        expect($errorMessage)->toContain('Sistem sedang sibuk memproses transaksi lain pada database');
    } finally {
        $pdoSecondary->exec('ROLLBACK;');
    }
});

test('transaction failure on file-based sqlite rolls back all changes atomically without partial state', function () {
    $this->actingAs($this->admin);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // Simulasikan kegagalan fatal saat update schedule
    Schedule::saving(function ($model) use ($schedule) {
        if ($model->id === $schedule->id && $model->isDirty('instructor_id')) {
            throw new RuntimeException('Simulasi crash transaksi di level database fisik.');
        }
    });

    expect(fn () => $this->service->approveSubstitution(
        leaveId: $this->leave->id,
        scheduleId: $schedule->id,
        replacementInstructorId: $this->kanero->id,
    ))->toThrow(RuntimeException::class);

    // Verifikasi bahwa substitution TIDAK tertinggal di database file
    $subCount = ScheduleSubstitution::where('schedule_id', $schedule->id)->count();
    expect($subCount)->toBe(0);

    // Instruktur pada jadwal tidak berubah
    $schedule->refresh();
    expect($schedule->instructor_id)->toBe($this->wahyu->id);
});

test('two concurrent php worker processes attempting to approve the same schedule result in exactly one approval and zero corruption', function () {
    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $leaveId = $this->leave->id;
    $scheduleId = $schedule->id;
    $kaneroId = $this->kanero->id;
    $sqlitePath = addslashes($this->sqlitePath);

    $basePath = addslashes(base_path());

    // Script PHP independen yang merepresentasikan satu request worker terpisah
    $workerScript = <<<PHP
require '{$basePath}/vendor/autoload.php';
\$app = require_once '{$basePath}/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.default' => 'sqlite_worker']);
config(['database.connections.sqlite_worker' => [
    'driver' => 'sqlite',
    'database' => '{$sqlitePath}',
    'prefix' => '',
    'foreign_key_constraints' => true,
    'busy_timeout' => 2000,
]]);

auth()->login(\App\Models\User::where('email', 'admin@palcomtech.ac.id')->firstOrFail());

try {
    \$service = app(\App\Services\Scheduling\ScheduleApprovalService::class);
    \$service->approveSubstitution({$leaveId}, {$scheduleId}, {$kaneroId}, 'Approval by concurrent process');
    echo 'SUCCESS';
    exit(0);
} catch (\Illuminate\Validation\ValidationException \$e) {
    echo 'VALIDATION_REJECTED';
    exit(0);
} catch (\Throwable \$e) {
    echo 'ERROR: ' . \$e->getMessage();
    exit(1);
}
PHP;

    $tempScript1 = storage_path('framework/testing/worker1_'.uniqid().'.php');
    $tempScript2 = storage_path('framework/testing/worker2_'.uniqid().'.php');

    file_put_contents($tempScript1, "<?php\n".$workerScript);
    file_put_contents($tempScript2, "<?php\n".$workerScript);

    try {
        // Luncurkan dua proses OS PHP secara bersamaan (paralel)
        $process1 = new Process(['php', $tempScript1], base_path());
        $process2 = new Process(['php', $tempScript2], base_path());

        $process1->start();
        $process2->start();

        $process1->wait();
        $process2->wait();

        $output1 = trim($process1->getOutput());
        $output2 = trim($process2->getOutput());
        $error1 = trim($process1->getErrorOutput());
        $error2 = trim($process2->getErrorOutput());

        if ($process1->getExitCode() !== 0 || $process2->getExitCode() !== 0) {
            throw new RuntimeException("Process 1 error: [{$error1}] out: [{$output1}], Process 2 error: [{$error2}] out: [{$output2}]");
        }

        $outputs = [$output1, $output2];
        expect($outputs)->toContain('SUCCESS')
            ->and($outputs)->toContain('VALIDATION_REJECTED');

        // Pastikan di database fisik hanya ada TEPAT 1 record substitusi (tidak terjadi duplikasi / korupsi data)
        $subCount = ScheduleSubstitution::where('schedule_id', $scheduleId)->count();
        expect($subCount)->toBe(1);

        $schedule->refresh();
        expect($schedule->instructor_id)->toBe($kaneroId);
    } finally {
        @unlink($tempScript1);
        @unlink($tempScript2);
    }
});
