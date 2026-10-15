<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('requires authentication to access the Activity Log preview', function () {
    $this->get(route('activity-log'))
        ->assertRedirect(route('login'));
});

it('shows the Activity Log preview to an authenticated and verified user', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)
        ->get(route('activity-log'))
        ->assertOk()
        ->assertSee('Activity Log');

    if (Schema::hasTable('activity_logs')) {
        $response->assertSee('Riwayat Aktivitas');
    } else {
        $response->assertSee('Data simulasi');
    }
});
