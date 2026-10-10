<?php

use App\Models\User;

it('requires login to open the Approval and Reject preview', function () {
    $this->get(route('approval-review'))
        ->assertRedirect(route('login'));
});

it('shows the simulated approval workflow to authenticated and verified users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('approval-review'))
        ->assertOk()
        ->assertSee('Approval / Reject')
        ->assertSee('Data simulasi')
        ->assertSee('Pilih Instruktur Pengganti')
        ->assertSee('Setujui Rekomendasi')
        ->assertSee('Tolak Rekomendasi')
        ->assertSee('Alasan penolakan')
        ->assertSee('Tidak mengubah jadwal');
});

it('requires verification for the preview route', function () {
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($unverified)
        ->get(route('approval-review'))
        ->assertRedirect(route('verification.notice'));
});