<?php

use App\Livewire\DashboardMonitor;
use App\Models\InstructorLeave;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication and verified email to open the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

it('shows the monitoring component without demo agendas', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(DashboardMonitor::class)
        ->assertSee('Kalender mengajar')
        ->assertSee('Status pengajuan izin')
        ->assertDontSee('Instruktur contoh A')
        ->assertDontSee('Pratinjau • Data contoh');
});

it('displays saved schedules on the selected date', function () {
    Schedule::factory()->create([
        'date' => '2026-10-15', 'start_time' => '08:00', 'end_time' => '10:00', 'status' => 'scheduled',
    ]);
    Schedule::factory()->create(['date' => '2026-10-16']);
    $this->actingAs(User::factory()->create());

    Livewire::test(DashboardMonitor::class)
        ->set('month', '2026-10')
        ->call('selectDate', '2026-10-15')
        ->assertSee('15 Oktober 2026')
        ->assertSee('08:00–10:00 WIB')
        ->assertSee('Terjadwal')
        ->assertSee('1 sesi tercatat pada tanggal ini');
});

it('shows calendar session counts when stored dates contain times', function (string $date) {
    Schedule::factory()->count(4)->create(['date' => $date.' 00:00:00']);
    Schedule::factory()->create(['date' => $date.' 12:00:00']);
    Schedule::factory()->create(['date' => '2026-09-30 23:59:59']);
    Schedule::factory()->create(['date' => '2026-11-01 00:00:00']);
    $this->actingAs(User::factory()->create());
    $this->assertDatabaseHas('schedules', ['date' => $date.' 00:00:00']);
    $this->assertDatabaseHas('schedules', ['date' => $date.' 12:00:00']);

    Livewire::test(DashboardMonitor::class)
        ->set('month', '2026-10')
        ->call('selectDate', $date)
        ->assertSeeHtml('aria-label="'.$date.': 5 sesi"')
        ->assertSee('5 sesi tercatat pada tanggal ini')
        ->assertViewHas('monthlyScheduleCount', 5);
})->with([
    'first day of month' => '2026-10-01',
    'reported date' => '2026-10-15',
    'last day of month' => '2026-10-31',
]);

it('changes months and selects the first day of the new month', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(DashboardMonitor::class)
        ->set('month', '2026-10')
        ->call('nextMonth')
        ->assertSet('month', '2026-11')
        ->assertSet('selectedDate', '2026-11-01')
        ->assertSee('November 2026')
        ->call('previousMonth')
        ->assertSet('month', '2026-10')
        ->assertSet('selectedDate', '2026-10-01');
});

it('rejects invalid or off-month selected dates', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(DashboardMonitor::class)
        ->set('month', '2026-10')
        ->set('selectedDate', '2026-10-01')
        ->call('selectDate', '2026-11-15')
        ->assertSet('selectedDate', '2026-10-01')
        ->call('selectDate', '2026-10-40')
        ->assertSet('selectedDate', '2026-10-01');
});

it('shows accurate monthly totals without including another month', function () {
    Schedule::factory()->create(['date' => '2026-10-15']);
    Schedule::factory()->create(['date' => '2026-10-15']);
    Schedule::factory()->create(['date' => '2026-11-01']);
    $this->actingAs(User::factory()->create());

    Livewire::test(DashboardMonitor::class)
        ->set('month', '2026-10')
        ->assertSee('Oktober 2026')
        ->assertViewHas('monthlyScheduleCount', 2)
        ->assertViewHas('totalSchedules', 3);
});

it('distinguishes leave statuses from schedule substitution decisions', function () {
    InstructorLeave::factory()->create(['status' => 'pending']);
    InstructorLeave::factory()->create(['status' => 'approved']);
    ScheduleSubstitution::factory()->rejected()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test(DashboardMonitor::class)
        ->assertViewHas('totalLeaves', 3)
        ->assertViewHas('leaveCounts', fn ($counts) => (int) $counts['pending'] === 1 && (int) $counts['approved'] === 2)
        ->assertViewHas('substitutionCounts', fn ($counts) => (int) $counts['rejected'] === 1);
});

it('shows an empty agenda rather than fabricated classes', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(DashboardMonitor::class)
        ->set('month', '2026-10')
        ->call('selectDate', '2026-10-15')
        ->assertSee('Tidak ada jadwal pada tanggal ini.')
        ->assertDontSee('Instruktur contoh');
});

it('is read only and links to the existing scheduler', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Buka AI Scheduler')
        ->assertSee('Ringkasan hasil analisis AI belum tersedia')
        ->assertDontSee('wire:click="approveSubstitution')
        ->assertDontSee('wire:click="delete');
});
