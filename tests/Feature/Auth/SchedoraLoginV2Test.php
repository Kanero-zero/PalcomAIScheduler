<?php

use App\Models\User;

it('shows the SCHEDORA login form directly at the homepage for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('SCHEDORA AI')
        ->assertSee('Alur Kerja Cerdas')
        ->assertSee('Masuk ke SCHEDORA AI')
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee('Agenda Pembelajaran');
});

it('preserves the Fortify login route and posts to its existing login handler', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('method="POST"', false)
        ->assertSee('action="'.route('login.store').'"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="remember"', false);
});

it('routes an already-authenticated user away from the public homepage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')
        ->assertRedirect(route('dashboard'));
});

it('still accepts valid credentials through Laravel Fortify', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('does not expose public signup on the login homepage', function () {
    $this->get('/')->assertOk()
        ->assertDontSee('Buat Akun')
        ->assertDontSee('Daftar sekarang');
});
