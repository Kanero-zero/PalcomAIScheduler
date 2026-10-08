<?php

use App\Models\User;

test('authenticated user can access all palcom navigation routes', function (string $routeName) {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertOk();
})->with([
    'dashboard',
    'ai-scheduler',
    'schedules.index',
    'instructors.index',
    'rooms.index',
    'course-classes.index',
    'instructor-leaves.index',
]);
