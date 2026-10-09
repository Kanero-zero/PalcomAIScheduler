<?php

use App\Livewire\AiScheduler;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\InstructorSkill;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->engine = app(SchedulingEngine::class);
});

test('it finds affected class schedules for an instructor leave', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    $affectedSchedules = $this->engine->findAffectedSchedules(
        instructorId: $wahyu->id,
        date: $this->engine->normalizeDate($leave->date),
        startTime: $leave->start_time,
        endTime: $leave->end_time,
    );

    expect($affectedSchedules)->toHaveCount(2);

    $subjects = $affectedSchedules->map(fn ($s) => $s->courseClass->subject)->toArray();
    expect($subjects)->toContain('Microsoft Excel', 'Microsoft Word');
});

test('it executes Wahyu demo scenario successfully and recommends Kanero and Budi', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    $result = $this->engine->evaluateLeave($leave);

    expect($result->instructorName)->toBe('Wahyu')
        ->and($result->totalAffectedSchedules)->toBe(2)
        ->and($result->totalResolvedSchedules)->toBe(2)
        ->and($result->allSchedulesResolved)->toBeTrue();

    // 1. Evaluasi Kelas Microsoft Excel (13:00 - 15:00)
    $excelEval = collect($result->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');
    expect($excelEval)->not->toBeNull()
        ->and($excelEval->status)->toBe('resolved')
        ->and($excelEval->hasCandidate())->toBeTrue()
        ->and($excelEval->bestCandidate->instructorName)->toBe('Kanero')
        ->and($excelEval->bestCandidate->skillLevel)->toBe('advanced')
        ->and($excelEval->bestCandidate->score)->toBe(110)
        ->and($excelEval->bestCandidate->reasons)->not->toBeEmpty();

    // Pastikan Budi Santoso juga kandidat valid (level intermediate)
    $validExcelCandidates = collect($excelEval->validCandidates)->pluck('instructorName')->toArray();
    expect($validExcelCandidates)->toContain('Kanero', 'Budi Santoso');

    // Pastikan Rizky Pratama dan Dina Oktavia didiskualifikasi
    $disqualifiedExcel = collect($excelEval->disqualifiedCandidates)->pluck('instructorName')->toArray();
    expect($disqualifiedExcel)->toContain('Rizky Pratama', 'Dina Oktavia');

    // Cek bahwa Rizky Pratama terdeteksi bentrok jadwal
    $rizkyEval = collect($excelEval->disqualifiedCandidates)->first(fn ($c) => $c->instructorName === 'Rizky Pratama');
    expect($rizkyEval->hasScheduleConflict)->toBeTrue();

    // 2. Evaluasi Kelas Microsoft Word (16:00 - 18:00)
    $wordEval = collect($result->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Word');
    expect($wordEval)->not->toBeNull()
        ->and($wordEval->status)->toBe('resolved')
        ->and($wordEval->bestCandidate->instructorName)->toBe('Kanero');

    // Budi Santoso tidak punya skill Word, jadi didiskualifikasi
    $disqualifiedWord = collect($wordEval->disqualifiedCandidates)->pluck('instructorName')->toArray();
    expect($disqualifiedWord)->toContain('Budi Santoso', 'Dina Oktavia', 'Rizky Pratama');
});

test('it disqualifies candidates with overlapping teaching schedules', function () {
    $date = '2026-11-05';

    // Instruktur utama & pengganti
    $mainInstructor = Instructor::factory()->create(['name' => 'Pak Guru Utama', 'status' => 'active']);
    $subCandidate = Instructor::factory()->create(['name' => 'Pak Pengganti Sibuk', 'status' => 'active']);

    // Sub kandidat punya kompetensi Python
    InstructorSkill::factory()->create([
        'instructor_id' => $subCandidate->id,
        'skill' => 'Python Programming',
        'level' => 'advanced',
    ]);

    $room = Room::factory()->create(['name' => 'Lab Komputer 3', 'capacity' => 30, 'status' => 'available']);
    $class = CourseClass::factory()->create(['name' => 'Kelas Python Dasar', 'subject' => 'Python Programming', 'student_count' => 15]);

    // Kelas utama yang terdampak (10:00 - 12:00)
    $affectedSchedule = Schedule::factory()->create([
        'instructor_id' => $mainInstructor->id,
        'course_class_id' => $class->id,
        'room_id' => $room->id,
        'date' => $date,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'status' => 'scheduled',
    ]);

    // Sub kandidat memiliki kelas lain yang BENTROK (11:00 - 13:00)
    $otherClass = CourseClass::factory()->create(['name' => 'Kelas Lain Bentrok', 'subject' => 'Other']);
    Schedule::factory()->create([
        'instructor_id' => $subCandidate->id,
        'course_class_id' => $otherClass->id,
        'room_id' => $room->id,
        'date' => $date,
        'start_time' => '11:00:00',
        'end_time' => '13:00:00',
        'status' => 'scheduled',
    ]);

    $eval = $this->engine->evaluateSchedule($affectedSchedule, $mainInstructor->id);

    expect($eval->hasCandidate())->toBeFalse()
        ->and($eval->status)->toBe('no_candidate');

    $candidateEval = collect($eval->disqualifiedCandidates)->first(fn ($c) => $c->instructorId === $subCandidate->id);
    expect($candidateEval)->not->toBeNull()
        ->and($candidateEval->hasScheduleConflict)->toBeTrue()
        ->and($candidateEval->competencyMatched)->toBeTrue()
        ->and($candidateEval->disqualificationReason)->toContain('Jadwal bentrok');
});

test('it disqualifies candidates with overlapping leaves', function () {
    $date = '2026-11-06';

    $mainInstructor = Instructor::factory()->create(['name' => 'Dosen A', 'status' => 'active']);
    $subCandidate = Instructor::factory()->create(['name' => 'Dosen B', 'status' => 'active']);

    InstructorSkill::factory()->create([
        'instructor_id' => $subCandidate->id,
        'skill' => 'Data Science',
        'level' => 'advanced',
    ]);

    $room = Room::factory()->create(['capacity' => 30, 'status' => 'available']);
    $class = CourseClass::factory()->create(['name' => 'Data Science 1', 'subject' => 'Data Science', 'student_count' => 15]);

    $affectedSchedule = Schedule::factory()->create([
        'instructor_id' => $mainInstructor->id,
        'course_class_id' => $class->id,
        'room_id' => $room->id,
        'date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'status' => 'scheduled',
    ]);

    // Sub kandidat sedang izin pada jam yang sama
    InstructorLeave::factory()->create([
        'instructor_id' => $subCandidate->id,
        'date' => $date,
        'start_time' => '08:30:00',
        'end_time' => '11:00:00',
        'status' => 'approved',
        'reason' => 'Sakit',
    ]);

    $eval = $this->engine->evaluateSchedule($affectedSchedule, $mainInstructor->id);

    $candidateEval = collect($eval->disqualifiedCandidates)->first(fn ($c) => $c->instructorId === $subCandidate->id);
    expect($candidateEval)->not->toBeNull()
        ->and($candidateEval->hasLeaveConflict)->toBeTrue()
        ->and($candidateEval->disqualificationReason)->toContain('Instruktur sedang mengajukan izin');
});

test('it provides no candidates status when all candidates lack competency', function () {
    $date = '2026-11-07';

    $mainInstructor = Instructor::factory()->create(['status' => 'active']);
    $room = Room::factory()->create(['capacity' => 30, 'status' => 'available']);

    // Mata kuliah langka yang tidak dimiliki instruktur manapun
    $rareClass = CourseClass::factory()->create([
        'name' => 'Quantum Computing Basics',
        'subject' => 'Quantum Computing',
        'student_count' => 10,
    ]);

    $affectedSchedule = Schedule::factory()->create([
        'instructor_id' => $mainInstructor->id,
        'course_class_id' => $rareClass->id,
        'room_id' => $room->id,
        'date' => $date,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'status' => 'scheduled',
    ]);

    $eval = $this->engine->evaluateSchedule($affectedSchedule, $mainInstructor->id);

    expect($eval->hasCandidate())->toBeFalse()
        ->and($eval->status)->toBe('no_candidate')
        ->and($eval->validCandidates)->toBeEmpty()
        ->and($eval->summary)->toContain('Belum ada instruktur pengganti yang memenuhi kualifikasi');
});

test('it verifies room suitability and capacity', function () {
    $date = '2026-11-08';

    $instructor = Instructor::factory()->create(['status' => 'active']);
    // Ruangan hanya muat 10, tapi siswa ada 25
    $smallRoom = Room::factory()->create(['name' => 'Ruang Sempit', 'capacity' => 10, 'status' => 'available']);
    $largeClass = CourseClass::factory()->create(['student_count' => 25]);

    $schedule = Schedule::factory()->create([
        'instructor_id' => $instructor->id,
        'course_class_id' => $largeClass->id,
        'room_id' => $smallRoom->id,
        'date' => $date,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
    ]);

    $roomEval = $this->engine->evaluateRoom($smallRoom, $schedule);

    expect($roomEval->isCapacitySufficient)->toBeFalse()
        ->and($roomEval->isValid)->toBeFalse()
        ->and($roomEval->notes)->toContain('Kapasitas ruangan (10) kurang dari jumlah siswa (25)');
});

test('it runs schedule:evaluate command successfully', function () {
    $wahyuLeave = InstructorLeave::firstOrFail();

    $this->artisan('schedule:evaluate', ['leave_id' => $wahyuLeave->id])
        ->expectsOutputToContain('PALCOM AI SCHEDULER - DETERMINISTIC ENGINE')
        ->expectsOutputToContain('Wahyu')
        ->expectsOutputToContain('Kanero')
        ->expectsOutputToContain('STATUS AKHIR: SUKSES')
        ->assertSuccessful();
});

test('it renders ai-scheduler page and livewire component for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ai-scheduler'))
        ->assertOk()
        ->assertSee('AI Auto-Scheduler')
        ->assertSee('Tahap 1: Deterministik Aktif');

    Livewire::test(AiScheduler::class)
        ->assertSet('selectedLeaveId', 1)
        ->assertSee('Wahyu')
        ->assertSee('Kanero')
        ->assertSee('Kelas Microsoft Excel - Reguler Siang')
        ->assertSee('Rekomendasi Utama')
        ->call('runScheduler')
        ->assertHasNoErrors();
});
