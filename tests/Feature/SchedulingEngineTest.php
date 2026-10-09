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

test('it detects room conflict and suggests available alternative room', function () {
    $date = '2026-11-10';
    $instructor = Instructor::where('name', 'Wahyu')->firstOrFail();
    $courseClass = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();

    // Lab 1 sedang dipakai kelas lain pada jam 13:00 - 15:00
    $conflictRoom = Room::where('name', 'Lab 1')->firstOrFail();
    $otherInstructor = Instructor::where('name', 'Dina Oktavia')->firstOrFail();
    $otherClass = CourseClass::where('subject', 'Desain Grafis')->firstOrFail();

    Schedule::factory()->create([
        'instructor_id' => $otherInstructor->id,
        'course_class_id' => $otherClass->id,
        'room_id' => $conflictRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    // Kelas terdampak yang ruangannya bentrok
    $affectedSchedule = Schedule::factory()->create([
        'instructor_id' => $instructor->id,
        'course_class_id' => $courseClass->id,
        'room_id' => $conflictRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    $eval = $this->engine->evaluateSchedule($affectedSchedule, $instructor->id);

    // Ruangan awal bentrok, tetapi sistem menemukan ruangan alternatif (misal Lab 2)
    expect($eval->roomEvaluation->hasRoomConflict)->toBeTrue()
        ->and($eval->roomEvaluation->isValid)->toBeFalse()
        ->and($eval->roomEvaluation->requiresRoomChange)->toBeTrue()
        ->and($eval->roomEvaluation->suggestedAlternativeRoom)->not->toBeNull()
        ->and($eval->isResolved())->toBeTrue()
        ->and($eval->status)->toBe('resolved')
        ->and($eval->summary)->toContain('disarankan dialihkan ke');
});

test('it sets room_issue status when initial room is invalid and no alternative room is available', function () {
    $date = '2026-11-11';
    $instructor = Instructor::where('name', 'Wahyu')->firstOrFail();
    $largeClass = CourseClass::factory()->create(['subject' => 'Microsoft Excel', 'student_count' => 100]);
    $smallRoom = Room::factory()->create(['name' => 'Ruang Kecil', 'capacity' => 10, 'status' => 'available']);

    // Set semua ruangan lain menjadi penuh / maintenance sehingga tidak ada alternatif kapasitas 100
    Room::query()->where('id', '!=', $smallRoom->id)->update(['status' => 'maintenance']);

    $affectedSchedule = Schedule::factory()->create([
        'instructor_id' => $instructor->id,
        'course_class_id' => $largeClass->id,
        'room_id' => $smallRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    $eval = $this->engine->evaluateSchedule($affectedSchedule, $instructor->id);

    expect($eval->hasCandidate())->toBeTrue()
        ->and($eval->hasValidRoom())->toBeFalse()
        ->and($eval->isResolved())->toBeFalse()
        ->and($eval->status)->toBe('room_issue')
        ->and($eval->summary)->toContain('namun ruangan bermasalah');
});

test('it detects potential double-booking when a candidate is assigned to two overlapping affected classes', function () {
    $date = '2026-11-12';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $excelClass1 = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $excelClass2 = CourseClass::factory()->create(['name' => 'Excel Paralel', 'subject' => 'Microsoft Excel', 'student_count' => 10]);
    $room1 = Room::where('name', 'Lab 1')->firstOrFail();
    $room2 = Room::where('name', 'Lab 2')->firstOrFail();

    // Wahyu memiliki dua kelas terdampak yang jadwalnya BERIRISAN (13:00 - 15:00 dan 14:00 - 16:00)
    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass1->id,
        'room_id' => $room1->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass2->id,
        'room_id' => $room2->id,
        'date' => $date,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'status' => 'scheduled',
    ]);

    $result = $this->engine->evaluate(
        instructorId: $wahyu->id,
        date: $date,
        startTime: '13:00:00',
        endTime: '16:00:00',
    );

    expect($result->totalAffectedSchedules)->toBe(2);

    $eval1 = $result->affectedSchedules[0];
    $eval2 = $result->affectedSchedules[1];

    // Kelas 1 mendapatkan Kanero (skor tertinggi)
    expect($eval1->bestCandidate->instructorName)->toBe('Kanero');

    // Kelas 2 mendeteksi Kanero bentrok dengan Kelas 1, sehingga dialihkan ke Budi Santoso
    expect($eval2->bestCandidate->instructorName)->toBe('Budi Santoso')
        ->and($eval2->warnings)->not->toBeEmpty()
        ->and($eval2->warnings[0])->toContain('berpotensi bentrok jika ditugaskan ke dua kelas terdampak sekaligus');
});

test('regression: consistency of status, has_candidate, is_resolved, and total_resolved when two overlapping classes have only one candidate', function () {
    $date = '2026-11-15';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $excelClass1 = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $excelClass2 = CourseClass::factory()->create(['name' => 'Excel Lanjutan', 'subject' => 'Microsoft Excel', 'student_count' => 10]);
    $room1 = Room::where('name', 'Lab 1')->firstOrFail();
    $room2 = Room::where('name', 'Lab 2')->firstOrFail();

    // Jadikan Budi Santoso inactive sehingga HANYA Kanero yang menjadi satu-satunya kandidat valid pengganti Excel
    Instructor::where('name', 'Budi Santoso')->update(['status' => 'inactive']);

    // Dua kelas Wahyu yang waktunya saling bertabrakan
    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass1->id,
        'room_id' => $room1->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass2->id,
        'room_id' => $room2->id,
        'date' => $date,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'status' => 'scheduled',
    ]);

    $result = $this->engine->evaluate(
        instructorId: $wahyu->id,
        date: $date,
        startTime: '13:00:00',
        endTime: '16:00:00',
    );

    expect($result->totalAffectedSchedules)->toBe(2)
        ->and($result->totalResolvedSchedules)->toBe(1)
        ->and($result->allSchedulesResolved)->toBeFalse();

    $eval1 = $result->affectedSchedules[0];
    $eval2 = $result->affectedSchedules[1];

    // Kelas 1 mendapatkan Kanero dan resolved
    expect($eval1->status)->toBe('resolved')
        ->and($eval1->hasCandidate())->toBeTrue()
        ->and($eval1->isResolved())->toBeTrue()
        ->and($eval1->bestCandidate)->not->toBeNull()
        ->and($eval1->bestCandidate->instructorName)->toBe('Kanero')
        ->and($eval1->validCandidates)->not->toBeEmpty()
        ->and($eval1->toArray()['has_candidate'])->toBeTrue()
        ->and($eval1->toArray()['is_resolved'])->toBeTrue();

    // Kelas 2: Kanero bentrok dengan Kelas 1, tidak ada kandidat pengganti lain
    expect($eval2->bestCandidate)->toBeNull()
        ->and($eval2->validCandidates)->toBeEmpty()
        ->and($eval2->hasCandidate())->toBeFalse()
        ->and($eval2->status)->toBe('no_candidate')
        ->and($eval2->isResolved())->toBeFalse()
        ->and($eval2->toArray()['has_candidate'])->toBeFalse()
        ->and($eval2->toArray()['is_resolved'])->toBeFalse()
        ->and($eval2->toArray()['status'])->toBe('no_candidate');

    $disqualifiedNames = collect($eval2->disqualifiedCandidates)->pluck('instructorName')->toArray();
    expect($disqualifiedNames)->toContain('Kanero');
});

test('regression: two overlapping affected classes are not recommended the same alternative room', function () {
    $date = '2026-11-16';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $excelClass1 = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $excelClass2 = CourseClass::factory()->create(['name' => 'Excel Paralel B', 'subject' => 'Microsoft Excel', 'student_count' => 10]);

    // Ruangan awal Lab 1 bermasalah (ada kelas lain)
    $conflictRoom = Room::where('name', 'Lab 1')->firstOrFail();
    $otherInstructor = Instructor::where('name', 'Dina Oktavia')->firstOrFail();
    $otherClass = CourseClass::where('subject', 'Desain Grafis')->firstOrFail();

    Schedule::factory()->create([
        'instructor_id' => $otherInstructor->id,
        'course_class_id' => $otherClass->id,
        'room_id' => $conflictRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    // Kedua kelas terdampak awalnya ditempatkan di Lab 1 pada jam yang sama (13:00 - 15:00)
    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass1->id,
        'room_id' => $conflictRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass2->id,
        'room_id' => $conflictRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    $result = $this->engine->evaluate(
        instructorId: $wahyu->id,
        date: $date,
        startTime: '13:00:00',
        endTime: '15:00:00',
    );

    expect($result->totalAffectedSchedules)->toBe(2);

    $eval1 = $result->affectedSchedules[0];
    $eval2 = $result->affectedSchedules[1];

    $altRoom1 = $eval1->roomEvaluation?->suggestedAlternativeRoom;
    $altRoom2 = $eval2->roomEvaluation?->suggestedAlternativeRoom;

    expect($altRoom1)->not->toBeNull()
        ->and($altRoom2)->not->toBeNull()
        // Memastikan kedua kelas TIDAK direkomendasikan ruangan alternatif yang sama
        ->and($altRoom1->roomId)->not->toBe($altRoom2->roomId)
        ->and($eval1->isResolved())->toBeTrue()
        ->and($eval2->isResolved())->toBeTrue()
        ->and($result->totalResolvedSchedules)->toBe(2);
});

test('regression: second overlapping class gets room_issue when only one alternative room is available', function () {
    $date = '2026-11-17';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $excelClass1 = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $excelClass2 = CourseClass::factory()->create(['name' => 'Excel Paralel C', 'subject' => 'Microsoft Excel', 'student_count' => 10]);

    $conflictRoom = Room::where('name', 'Lab 1')->firstOrFail();
    $onlyAltRoom = Room::where('name', 'Lab 2')->firstOrFail();

    // Buat semua ruangan selain Lab 1 dan Lab 2 berstatus maintenance
    Room::query()->whereNotIn('id', [$conflictRoom->id, $onlyAltRoom->id])->update(['status' => 'maintenance']);

    // Ruangan Lab 1 bentrok dengan kelas lain pada 13:00 - 15:00
    $otherInstructor = Instructor::where('name', 'Dina Oktavia')->firstOrFail();
    $otherClass = CourseClass::where('subject', 'Desain Grafis')->firstOrFail();
    Schedule::factory()->create([
        'instructor_id' => $otherInstructor->id,
        'course_class_id' => $otherClass->id,
        'room_id' => $conflictRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    // Dua kelas terdampak yang sama-sama bermasalah di Lab 1 pada 13:00 - 15:00
    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass1->id,
        'room_id' => $conflictRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass2->id,
        'room_id' => $conflictRoom->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    $result = $this->engine->evaluate(
        instructorId: $wahyu->id,
        date: $date,
        startTime: '13:00:00',
        endTime: '15:00:00',
    );

    expect($result->totalAffectedSchedules)->toBe(2)
        ->and($result->totalResolvedSchedules)->toBe(1)
        ->and($result->allSchedulesResolved)->toBeFalse();

    $eval1 = $result->affectedSchedules[0];
    $eval2 = $result->affectedSchedules[1];

    // Kelas 1 mengambil Lab 2 sebagai alternatif
    expect($eval1->roomEvaluation->suggestedAlternativeRoom?->roomId)->toBe($onlyAltRoom->id)
        ->and($eval1->status)->toBe('resolved')
        ->and($eval1->isResolved())->toBeTrue();

    // Kelas 2 tidak mendapatkan ruangan karena Lab 2 sudah teralokasi ke Kelas 1 dan tidak ada alternatif lain
    expect($eval2->roomEvaluation->suggestedAlternativeRoom)->toBeNull()
        ->and($eval2->roomEvaluation->hasUsableRoom())->toBeFalse()
        ->and($eval2->hasValidRoom())->toBeFalse()
        ->and($eval2->status)->toBe('room_issue')
        ->and($eval2->isResolved())->toBeFalse()
        ->and($eval2->toArray()['has_valid_room'])->toBeFalse()
        ->and($eval2->toArray()['is_resolved'])->toBeFalse()
        ->and($eval2->toArray()['status'])->toBe('room_issue');
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
