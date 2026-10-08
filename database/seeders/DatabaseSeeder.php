<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Akun Login untuk Admin BAAK PalComTech
        User::updateOrCreate(
            ['email' => 'admin@palcomtech.ac.id'],
            [
                'name' => 'Admin BAAK PalComTech',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        // Akun Login Cadangan (Demo)
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Demo',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        // 2. Data Master & Transaksi Penjadwalan
        $this->call([
            InstructorSeeder::class,
            InstructorSkillSeeder::class,
            RoomSeeder::class,
            CourseClassSeeder::class,
            ScheduleSeeder::class,
            InstructorLeaveSeeder::class,
        ]);
    }
}
