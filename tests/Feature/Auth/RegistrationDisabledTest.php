<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('public registration routes are completely disabled and return 404', function () {
    expect(Route::has('register'))->toBeFalse();
    expect(Route::has('register.store'))->toBeFalse();

    $this->get('/register')
        ->assertNotFound();
});

test('posting to register endpoint returns 404 and does not create any user', function () {
    $initialUserCount = User::count();

    $response = $this->post('/register', [
        'name' => 'Unauthorized User',
        'email' => 'unauthorized@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $response->assertNotFound();

    expect(User::count())->toBe($initialUserCount);
    $this->assertDatabaseMissing('users', [
        'email' => 'unauthorized@example.com',
    ]);
});

test('login page does not display sign up link when public registration is disabled', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertDontSee('Sign up');
    $response->assertDontSee('Don\'t have an account?');
});

test('welcome page does not display register link when public registration is disabled', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('>Register<', false);
});
