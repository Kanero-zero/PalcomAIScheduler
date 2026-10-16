<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->demoDatabasePath = storage_path('framework/testing/demo-scenarios-'.uniqid().'.sqlite');

    config([
        'database.connections.demo.database' => $this->demoDatabasePath,
        'database.connections.demo.url' => null,
    ]);
});

afterEach(function () {
    app()['env'] = 'testing';
    DB::purge('demo');
    File::delete($this->demoDatabasePath);
});

test('command seeds the isolated demo database without changing the operational database', function () {
    $operationalUser = User::factory()->create([
        'email' => 'operational@example.test',
    ]);

    $this->artisan('scheduler:demo-data')
        ->assertExitCode(0);

    expect(DB::getDefaultConnection())->toBe('sqlite')
        ->and(User::find($operationalUser->id)?->email)->toBe('operational@example.test')
        ->and(DB::connection('demo')->table('users')->where('email', 'admin@palcomtech.ac.id')->exists())->toBeTrue()
        ->and(DB::connection('demo')->table('schedule_proposals')->count())->toBe(6);
});

test('command remains idempotent when the isolated demo database is seeded twice', function () {
    $this->artisan('scheduler:demo-data')->assertExitCode(0);

    $firstCounts = [
        'users' => DB::connection('demo')->table('users')->count(),
        'rooms' => DB::connection('demo')->table('rooms')->count(),
        'schedules' => DB::connection('demo')->table('schedules')->count(),
        'proposals' => DB::connection('demo')->table('schedule_proposals')->count(),
    ];

    $this->artisan('scheduler:demo-data')->assertExitCode(0);

    $secondCounts = [
        'users' => DB::connection('demo')->table('users')->count(),
        'rooms' => DB::connection('demo')->table('rooms')->count(),
        'schedules' => DB::connection('demo')->table('schedules')->count(),
        'proposals' => DB::connection('demo')->table('schedule_proposals')->count(),
    ];

    expect($secondCounts)->toBe($firstCounts)
        ->and(DB::getDefaultConnection())->toBe('sqlite');
});

test('command refuses to use the operational sqlite database', function () {
    config([
        'database.connections.demo.database' => config('database.connections.sqlite.database'),
    ]);

    $this->artisan('scheduler:demo-data')
        ->expectsOutput('Target database demo sama dengan database operasional. Eksekusi dibatalkan.')
        ->assertExitCode(1);

    expect(DB::getDefaultConnection())->toBe('sqlite');
});

test('command is blocked in production before creating the demo database', function () {
    app()['env'] = 'production';

    $this->artisan('scheduler:demo-data')
        ->expectsOutput('Perintah scheduler:demo-data dilarang dijalankan pada lingkungan production.')
        ->assertExitCode(1);

    expect(File::exists($this->demoDatabasePath))->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('sqlite');
});
