<?php

use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;

test('database seeder creates admin accounts and sample palcom data', function () {
    $this->seed(DatabaseSeeder::class);

    // 1. Verifikasi Login Admin
    $admin = User::where('email', 'admin@palcomtech.ac.id')->first();
    expect($admin)->not->toBeNull();

    $canLogin = Auth::attempt([
        'email' => 'admin@palcomtech.ac.id',
        'password' => 'password',
    ]);
    expect($canLogin)->toBeTrue();

    // 2. Verifikasi Instruktur & Skill Sesuai Skenario
    $wahyu = Instructor::where('name', 'Wahyu')->first();
    $kanero = Instructor::where('name', 'Kanero')->first();

    expect($wahyu)->not->toBeNull()
        ->and($kanero)->not->toBeNull()
        ->and($wahyu->skills->pluck('skill')->toArray())->toContain('Microsoft Excel', 'Microsoft Word')
        ->and($kanero->skills->pluck('skill')->toArray())->toContain('Microsoft Excel', 'Microsoft Word');

    // 3. Verifikasi Ruangan Lab
    expect(Room::count())->toBeGreaterThanOrEqual(4);

    // 4. Verifikasi Jadwal & Izin
    expect(Schedule::where('instructor_id', $wahyu->id)->count())->toBe(2)
        ->and(InstructorLeave::where('instructor_id', $wahyu->id)->count())->toBe(1);
});
