<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated dashboard renders the Schedora AI sidebar with preserved navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('SCHEDORA AI')
        ->assertSee('ACADEMIC SCHEDULING')
        ->assertSee('AI Auto-Scheduler')
        ->assertSee('Approval / Reject')
        ->assertSee('Activity Log')
        ->assertSee('MANAJEMEN AKADEMIK');
});

test('sidebar explicitly marks three future menus as disabled', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('Pengajuan Saya')
        ->toContain('Jadwal Mengajar')
        ->toContain('Notifikasi')
        ->toContain('Untuk pengembangan selanjutnya')
        ->toContain('schedora-nav-item is-disabled')
        ->toContain('disabled');
});

test('sidebar assets and existing logout action remain accessible', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('schedora/sidebar-v2.css')
        ->assertSee('schedora/sidebar-v2.js')
        ->assertSee('schedora/schedora-logo.png')
        ->assertSee(route('logout'));
});
