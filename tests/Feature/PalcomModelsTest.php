<?php

use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\InstructorSkill;
use App\Models\Room;
use App\Models\Schedule;

test('instructor has skills, schedules, and leaves relationships', function () {
    $instructor = Instructor::factory()->create([
        'name' => 'Wahyu',
        'status' => 'active',
    ]);

    $skill = InstructorSkill::factory()->create([
        'instructor_id' => $instructor->id,
        'skill' => 'Microsoft Excel',
        'level' => 'advanced',
    ]);

    $room = Room::factory()->create(['name' => 'Lab 1', 'capacity' => 20]);
    $courseClass = CourseClass::factory()->create(['name' => 'Excel Batch 1', 'subject' => 'Microsoft Excel']);

    $schedule = Schedule::factory()->create([
        'course_class_id' => $courseClass->id,
        'instructor_id' => $instructor->id,
        'room_id' => $room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);

    $leave = InstructorLeave::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '18:00',
        'reason' => 'Keperluan Keluarga',
    ]);

    expect($instructor->skills)->toHaveCount(1)
        ->and($instructor->skills->first()->skill)->toBe('Microsoft Excel')
        ->and($skill->instructor->id)->toBe($instructor->id)
        ->and($instructor->schedules)->toHaveCount(1)
        ->and($schedule->courseClass->id)->toBe($courseClass->id)
        ->and($schedule->instructor->id)->toBe($instructor->id)
        ->and($schedule->room->id)->toBe($room->id)
        ->and($instructor->leaves)->toHaveCount(1)
        ->and($leave->instructor->id)->toBe($instructor->id)
        ->and($room->schedules)->toHaveCount(1)
        ->and($courseClass->schedules)->toHaveCount(1);
});
