<?php

use App\Models\User;

test('baak dashboard v2 preview requires authentication and verified email', function () {
    $this->get(route('dashboard.v2-preview'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard.v2-preview'))
        ->assertRedirect(route('verification.notice'));
});

test('baak dashboard v2 preview has approved hierarchy and transparent simulation label', function () {
    $this->actingAs(User::factory()->create(['role' => 'baak']))
        ->get(route('dashboard.v2-preview'))
        ->assertOk()
        ->assertSee('Dashboard Operasional')
        ->assertSee('AI Priority Inbox')
        ->assertSee('Agenda Hari Ini')
        ->assertSee('Kalender Mengajar Bulanan')
        ->assertSee('DATA SIMULASI — BUKAN DATA OPERASIONAL')
        ->assertSee('UI PREVIEW');
});

test('dashboard v2 route is separate and does not replace operational dashboard', function () {
    expect(route('dashboard.v2-preview'))->not->toBe(route('dashboard'));

    $this->actingAs(User::factory()->create(['role' => 'baak']))
        ->get(route('dashboard'))
        ->assertOk();
});

test('priority inbox uses clearly labeled mock dataset and rule ordering with category filter', function () {
    $this->actingAs(User::factory()->create(['role' => 'baak']))
        ->get(route('dashboard.v2-preview'))
        ->assertOk()
        ->assertSee('Simulasi berbasis aturan, bukan keluaran AI')
        ->assertSee('Pengajuan Web Programming Pagi')
        ->assertSee('Pergantian Instruktur SpeakOut')
        ->assertSee('dashboard-v2-inbox-filter')
        ->assertSee('visiblePriorityItems')
        ->assertSee('formatDemoDate')
        ->assertSee('Buka Approval / Reject operasional');
});

test('dashboard preview agenda is marked as fictitious and no decisions are executed here', function () {
    $this->actingAs(User::factory()->create(['role' => 'baak']))
        ->get(route('dashboard.v2-preview'))
        ->assertOk()
        ->assertSee('Agenda Hari Ini (Contoh)')
        ->assertSee('Agenda ilustratif tanggal 23 November 2026')
        ->assertSee('persetujuan hanya dilakukan di modul Approval / Reject');
});

test('calendar preview is interactive and its illustrative sessions are not mistaken for free slots', function () {
    $this->actingAs(User::factory()->create(['role' => 'baak']))
        ->get(route('dashboard.v2-preview'))
        ->assertOk()
        ->assertSee('Kalender simulasi November 2026')
        ->assertSee('Kembali ke November demo')
        ->assertSee('calendarDays')
        ->assertSee('selectedCalendarSessions')
        ->assertSee('Ini tidak berarti ruangan kosong');
});

test('dashboard preview remains isolated from live dashboard component and DB mutations', function () {
    $preview = file_get_contents(resource_path('views/pages/dashboard-v2-preview.blade.php'));
    $legacy = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($preview)
        ->not->toContain('<livewire:dashboard-monitor />')
        ->not->toContain('wire:click="approve')
        ->not->toContain('wire:click="reject')
        ->toContain('DATA SIMULASI — BUKAN DATA OPERASIONAL')
        ->and($legacy)
        ->toContain('<livewire:dashboard-monitor />');
});
