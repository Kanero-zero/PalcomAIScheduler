<?php

use App\Livewire\ScheduleList;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Livewire\Livewire;

function createScheduleListFixture(string $name, string $subject, string $date, string $instructorName, string $status = 'scheduled'): Schedule
{
    $class = CourseClass::factory()->create(['name' => $name, 'subject' => $subject, 'student_count' => 12]);
    $instructor = Instructor::factory()->create(['name' => $instructorName]);
    $room = Room::factory()->create(['name' => 'Lab '.uniqid()]);

    return Schedule::factory()->for($class, 'courseClass')->for($instructor)->for($room)->create([
        'date' => $date,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'status' => $status,
    ]);
}

it('requires login to view schedules', function () {
    $this->get(route('schedules.index'))->assertRedirect(route('login'));
});

it('requires verified email to view schedules', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('schedules.index'))->assertRedirect(route('verification.notice'));
});

it('shows saved schedule data and its related entities', function () {
    createScheduleListFixture('Reguler Pagi', 'Microsoft Excel', '2026-10-20', 'Wahyu Saputra');
    $this->actingAs(User::factory()->create())
        ->get(route('schedules.index'))
        ->assertOk()
        ->assertSeeLivewire(ScheduleList::class)
        ->assertSee('Reguler Pagi')
        ->assertSee('Microsoft Excel')
        ->assertSee('Wahyu Saputra')
        ->assertSee('20/10/2026')
        ->assertSee('09:00–11:00 WIB')
        ->assertSee('Tampilan hanya baca')
        ->assertDontSee('Tampilan jadwal belum tersedia.');
});

it('searches both class names and subjects', function () {
    createScheduleListFixture('Kelas Alpha', 'Microsoft Excel', '2026-10-20', 'Wahyu');
    createScheduleListFixture('Kelas Beta', 'Desain Grafis', '2026-10-21', 'Kanero');
    $this->actingAs(User::factory()->create());

    Livewire::test(ScheduleList::class)
        ->set('search', 'Alpha')->assertSee('Kelas Alpha')->assertDontSee('Kelas Beta')
        ->set('search', 'Grafis')->assertSee('Kelas Beta')->assertDontSee('Kelas Alpha');
});

it('filters by date', function () {
    createScheduleListFixture('Jadwal Oktober', 'Excel', '2026-10-20', 'Wahyu');
    createScheduleListFixture('Jadwal November', 'Word', '2026-11-20', 'Kanero');
    $this->actingAs(User::factory()->create());

    Livewire::test(ScheduleList::class)
        ->set('date', '2026-11-20')
        ->assertSee('Jadwal November')
        ->assertDontSee('Jadwal Oktober');
});

it('filters by instructor', function () {
    $first = createScheduleListFixture('Kelas Wahyu', 'Excel', '2026-10-20', 'Wahyu');
    createScheduleListFixture('Kelas Kanero', 'Word', '2026-10-20', 'Kanero');
    $this->actingAs(User::factory()->create());

    Livewire::test(ScheduleList::class)
        ->set('instructorId', (string) $first->instructor_id)
        ->assertSee('Kelas Wahyu')
        ->assertDontSee('Kelas Kanero');
});

it('offers and filters by statuses present in the database', function () {
    createScheduleListFixture('Kelas Aktif', 'Excel', '2026-10-20', 'Wahyu');
    createScheduleListFixture('Kelas Batal', 'Word', '2026-10-21', 'Kanero', 'cancelled');
    $this->actingAs(User::factory()->create());

    Livewire::test(ScheduleList::class)
        ->assertSee('Cancelled')
        ->set('status', 'cancelled')
        ->assertSee('Kelas Batal')
        ->assertDontSee('Kelas Aktif');
});

it('combines filters and resets them', function () {
    $target = createScheduleListFixture('Excel Pilihan', 'Excel', '2026-10-20', 'Wahyu');
    createScheduleListFixture('Excel Lain', 'Excel', '2026-11-20', 'Kanero');
    $this->actingAs(User::factory()->create());

    Livewire::test(ScheduleList::class)
        ->set('search', 'Excel')
        ->set('date', '2026-10-20')
        ->set('instructorId', (string) $target->instructor_id)
        ->set('status', 'scheduled')
        ->assertSee('Excel Pilihan')->assertDontSee('Excel Lain')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('date', '')
        ->assertSet('instructorId', '')
        ->assertSet('status', '')
        ->assertSee('Excel Lain');
});

it('paginates results and resets the page when filtering', function () {
    $instructor = Instructor::factory()->create(['name' => 'Instruktur Pagination']);
    $room = Room::factory()->create(['name' => 'Lab Pagination']);
    foreach (range(1, 11) as $index) {
        $class = CourseClass::factory()->create([
            'name' => $index === 1 ? 'Kelas Pertama' : ($index === 11 ? 'Kelas Terakhir' : 'Kelas Nomor '.$index),
            'subject' => 'Excel',
        ]);
        Schedule::factory()->for($class, 'courseClass')->for($instructor)->for($room)->create([
            'date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
    }
    $this->actingAs(User::factory()->create());

    Livewire::test(ScheduleList::class)
        ->assertSee('Kelas Pertama')->assertDontSee('Kelas Terakhir')
        ->call('nextPage')->assertSee('Kelas Terakhir')->assertDontSee('Kelas Pertama')
        ->set('search', 'Kelas Pertama')
        ->assertSee('Kelas Pertama')->assertDontSee('Kelas Terakhir');
});

it('shows appropriate empty states with and without saved schedules', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(ScheduleList::class)->assertSee('Belum ada jadwal mengajar.');

    createScheduleListFixture('Kelas Tersedia', 'Excel', '2026-10-20', 'Wahyu');
    Livewire::test(ScheduleList::class)
        ->set('search', 'Tidak Ditemukan')
        ->assertSee('Tidak ada jadwal yang sesuai dengan filter.');
});

it('reflects the current instructor after the schedule is updated externally', function () {
    $schedule = createScheduleListFixture('Kelas Pergantian', 'Excel', '2026-10-20', 'Instruktur Lama');
    $replacement = Instructor::factory()->create(['name' => 'Instruktur Pengganti']);
    $this->actingAs(User::factory()->create());

    Livewire::test(ScheduleList::class)->assertSee('Instruktur Lama')->assertDontSee('Instruktur Pengganti');
    $schedule->update(['instructor_id' => $replacement->id]);
    Livewire::test(ScheduleList::class)->assertSee('Instruktur Pengganti')->assertDontSee('Instruktur Lama');
});

it('does not expose write actions on the schedule page', function () {
    createScheduleListFixture('Kelas Read Only', 'Excel', '2026-10-20', 'Wahyu');
    $this->actingAs(User::factory()->create())
        ->get(route('schedules.index'))
        ->assertOk()
        ->assertDontSee('wire:click="delete')
        ->assertDontSee('Tambah Jadwal')
        ->assertDontSee('Hapus Jadwal');
});
