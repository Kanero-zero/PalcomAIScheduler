<?php

use App\Livewire\InstructorLeaveForm;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('it provides active instructors sorted alphabetically for dropdown', function () {
    // Tambahkan instruktur non-aktif untuk memastikan filter aktif berjalan
    Instructor::factory()->create(['name' => 'AAA Instruktur Nonaktif', 'status' => 'inactive']);

    $component = Livewire::test(InstructorLeaveForm::class);

    $instructors = $component->get('instructors');
    $instructorNames = $instructors->pluck('name')->toArray();

    // Pastikan instruktur nonaktif tidak masuk
    expect($instructorNames)->not->toContain('AAA Instruktur Nonaktif');

    // Pastikan terurut alfabetis
    $sortedNames = $instructorNames;
    sort($sortedNames);
    expect($instructorNames)->toBe($sortedNames);
});

test('it validates required fields and time constraints on submitLeave', function () {
    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', null)
        ->set('form.date', '')
        ->set('form.start_time', '')
        ->set('form.end_time', '')
        ->call('submitLeave')
        ->assertHasErrors([
            'form.instructor_id' => 'required',
            'form.date' => 'required',
            'form.start_time' => 'required',
            'form.end_time' => 'required',
        ]);
});

test('it validates that end_time must be after start_time', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();

    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', '2026-11-20')
        ->set('form.start_time', '15:00')
        ->set('form.end_time', '13:00')
        ->call('submitLeave')
        ->assertHasErrors(['form.end_time' => 'after']);
});

test('it validates that instructor_id must exist in instructors table', function () {
    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', 99999)
        ->set('form.date', '2026-11-20')
        ->set('form.start_time', '13:00')
        ->set('form.end_time', '15:00')
        ->call('submitLeave')
        ->assertHasErrors(['form.instructor_id' => 'exists']);
});

test('it successfully submits leave with initial status pending and returns evaluation result', function () {
    $date = '2026-11-25';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $excelClass = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $wordClass = CourseClass::where('subject', 'Microsoft Word')->firstOrFail();
    $room1 = Room::where('name', 'Lab 1')->firstOrFail();

    // Buat jadwal mengajar untuk Wahyu pada tanggal tersebut
    $schedule1 = Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass->id,
        'room_id' => $room1->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    $schedule2 = Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $wordClass->id,
        'room_id' => $room1->id,
        'date' => $date,
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'status' => 'scheduled',
    ]);

    $test = Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', $date)
        ->set('form.start_time', '13:00')
        ->set('form.end_time', '18:00')
        ->set('form.reason', 'Keperluan dinas luar kota')
        ->call('submitLeave')
        ->assertHasNoErrors();

    // 5. Pastikan data tersimpan di database dengan status awal 'pending'
    $savedLeave = InstructorLeave::where('instructor_id', $wahyu->id)
        ->whereDate('date', $date)
        ->where('start_time', '13:00')
        ->first();

    expect($savedLeave)->not->toBeNull()
        ->and($savedLeave->status)->toBe('pending')
        ->and($savedLeave->reason)->toBe('Keperluan dinas luar kota');

    // 6 & 7. Pastikan hasil evaluasi terisi sesuai kontrak API
    $result = $test->get('schedulingResult');
    expect($result)->not->toBeNull()
        ->and($result['leave_id'])->toBe($savedLeave->id)
        ->and($result['instructor_id'])->toBe($wahyu->id)
        ->and($result['instructor_name'])->toBe('Wahyu')
        ->and($result['total_affected_schedules'])->toBe(2)
        ->and($result['total_resolved_schedules'])->toBe(2)
        ->and($result['all_schedules_resolved'])->toBeTrue()
        ->and($result['affected_schedules'])->toHaveCount(2);

    $test->assertSee('Kanero');
});

test('it prevents duplicate overlapping leaves for the same instructor', function () {
    $date = '2026-11-26';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();

    // Buat izin pertama yang sudah tercatat
    InstructorLeave::factory()->create([
        'instructor_id' => $wahyu->id,
        'date' => $date,
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    // Coba ajukan izin kedua yang jamnya beririsan (14:00 - 18:00)
    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', $date)
        ->set('form.start_time', '14:00')
        ->set('form.end_time', '18:00')
        ->call('submitLeave')
        ->assertHasErrors(['form.instructor_id']);

    // Pastikan tidak ada record tambahan yang tersimpan
    expect(InstructorLeave::where('instructor_id', $wahyu->id)->whereDate('date', $date)->count())->toBe(1);
});

test('it handles scenario where instructor has no affected schedules', function () {
    $date = '2026-11-27';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();

    // Wahyu tidak memiliki jadwal apapun pada tanggal ini
    Schedule::where('instructor_id', $wahyu->id)->whereDate('date', $date)->delete();

    $test = Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', $date)
        ->set('form.start_time', '08:00')
        ->set('form.end_time', '12:00')
        ->set('form.reason', 'Cuti tahunan')
        ->call('submitLeave')
        ->assertHasNoErrors();

    // Izin tetap tersimpan di database dengan status pending
    $savedLeave = InstructorLeave::where('instructor_id', $wahyu->id)
        ->whereDate('date', $date)
        ->first();
    expect($savedLeave)->not->toBeNull()
        ->and($savedLeave->status)->toBe('pending');

    // Struktur hasil evaluasi untuk kondisi 0 jadwal terdampak
    $result = $test->get('schedulingResult');
    expect($result)->not->toBeNull()
        ->and($result['total_affected_schedules'])->toBe(0)
        ->and($result['total_resolved_schedules'])->toBe(0)
        ->and($result['all_schedules_resolved'])->toBeFalse()
        ->and($result['affected_schedules'])->toBeEmpty()
        ->and($result['summary'])->toContain('Tidak ada jadwal kelas yang terdampak');

    expect($test->get('feedbackMessage'))->toContain('Tidak ada jadwal kelas yang terdampak');
});

test('it only generates recommendations and does not modify original schedules in database', function () {
    $date = '2026-11-28';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $excelClass = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $room1 = Room::where('name', 'Lab 1')->firstOrFail();

    $schedule = Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass->id,
        'room_id' => $room1->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', $date)
        ->set('form.start_time', '13:00')
        ->set('form.end_time', '15:00')
        ->call('submitLeave');

    // Verifikasi data tabel schedules TIDAK berubah (masih instruktur Wahyu, bukan Kanero)
    $freshSchedule = $schedule->fresh();
    expect($freshSchedule->instructor_id)->toBe($wahyu->id)
        ->and($freshSchedule->room_id)->toBe($room1->id)
        ->and($freshSchedule->status)->toBe('scheduled');
});

test('it resets form fields and evaluation state on resetForm', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();

    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', '2026-11-29')
        ->set('form.start_time', '10:00')
        ->set('form.end_time', '12:00')
        ->set('form.reason', 'Testing reset')
        ->call('resetForm')
        ->assertSet('form.instructor_id', null)
        ->assertSet('form.date', '')
        ->assertSet('form.start_time', '')
        ->assertSet('form.end_time', '')
        ->assertSet('form.reason', '')
        ->assertSet('schedulingResult', null)
        ->assertSet('submittedLeaveId', null)
        ->assertSet('feedbackMessage', null);
});
