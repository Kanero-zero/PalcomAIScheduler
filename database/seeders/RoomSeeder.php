<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            ['id' => 1, 'name' => 'Lab 1', 'department' => 'computer', 'capacity' => 20, 'status' => 'available'],
            ['id' => 2, 'name' => 'Lab 2', 'department' => 'computer', 'capacity' => 12, 'status' => 'available'],
            ['id' => 3, 'name' => 'Lab 3', 'department' => 'computer', 'capacity' => 16, 'status' => 'available'],
            // Belum ada keputusan bisnis untuk bidang Ruang Teori 1. Jangan menimpa nilai existing.
            ['id' => 4, 'name' => 'Ruang Teori 1', 'capacity' => 30, 'status' => 'available'],
        ];

        foreach ($rooms as $room) {
            Room::updateOrCreate(['id' => $room['id']], $room);
        }
    }
}
