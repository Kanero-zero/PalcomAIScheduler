<?php

use App\Models\User;

it('requires authentication to access the Activity Log preview', function () {
    $this->get(route('activity-log'))
        ->assertRedirect(route('login'));
});

it('shows the Activity Log preview to an authenticated and verified user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('activity-log'))
        ->assertOk()
        ->assertSee('Activity Log')
        ->assertSee('Data simulasi')
        ->assertSee('Riwayat Aktivitas')
        ->assertSee('Filter jenis aktivitas')
        ->assertSee('Filter status');
});