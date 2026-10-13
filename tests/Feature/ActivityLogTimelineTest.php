<?php

use App\Livewire\ActivityLogTimeline;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('activity log route requires authentication and email verification', function () {
    $this->get(route('activity-log'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('activity-log'))
        ->assertRedirect(route('verification.notice'));
});

test('verified non admin cannot access activity log', function () {
    config()->set('auth.admin_emails', ['admin@palcom.test']);

    $this->actingAs(User::factory()->create(['email' => 'ordinary@palcom.test']))
        ->get(route('activity-log'))
        ->assertForbidden();
});

test('administrator can access activity log with no demo records', function () {
    config()->set('auth.admin_emails', ['admin@palcom.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@palcom.test']));

    $response = $this->get(route('activity-log'));
    $response->assertOk()->assertSeeLivewire(ActivityLogTimeline::class);
    $response->assertDontSee('Admin Demo');
    $response->assertDontSee('Pratinjau - Data simulasi');

    if (! class_exists(\App\Services\ActivityLog\ActivityLogService::class)
        || ! Schema::hasTable('activity_logs')) {
        $response->assertSee('Riwayat nyata belum tersedia');
    }
});

test('administrator can reset activity filters', function () {
    config()->set('auth.admin_emails', ['admin@palcom.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@palcom.test']));

    Livewire::test(ActivityLogTimeline::class)
        ->set('type', 'ai')
        ->set('state', 'fallback')
        ->call('resetFilters')
        ->assertSet('type', 'all')
        ->assertSet('state', 'all');
});

test('when backend is available sensitive activity log metadata stays hidden', function () {
    if (! class_exists(\App\Models\ActivityLog::class)) {
        $this->markTestSkipped('Backend PR #16 belum tersedia.');
    }

    config()->set('auth.admin_emails', ['admin@palcom.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@palcom.test']));

    \App\Models\ActivityLog::query()->create([
        'type' => 'leave',
        'state' => 'pending',
        'actor' => 'Admin Uji',
        'title' => 'Pengajuan izin instruktur dicatat',
        'description' => 'Sangat rahasia: DIAGNOSIS_INTERNAL_XYZ.',
        'subject' => 'Pengajuan Izin #123',
        'metadata' => ['reason' => 'DIAGNOSIS_INTERNAL_XYZ'],
    ]);

    $this->get(route('activity-log'))
        ->assertOk()
        ->assertSee('Pengajuan Izin #123')
        ->assertDontSee('DIAGNOSIS_INTERNAL_XYZ');
});
