<?php

use App\Models\Competency;
use App\Models\CurriculumPeriod;
use App\Models\CurriculumSubject;
use App\Models\CurriculumVersion;
use App\Models\Facility;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorSkill;
use App\Models\Program;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('d4_a master data read contract queries retrieve expected entities by department', function () {
    // 1. Setup Master Data
    $progComp = Program::create(['code' => 'PROG-COMP', 'name' => 'Komputer Dasar', 'department' => 'computer', 'is_active' => true]);
    $progEng = Program::create(['code' => 'PROG-ENG', 'name' => 'Speakout Reguler', 'department' => 'speakout', 'is_active' => true]);

    $verPub = CurriculumVersion::create(['program_id' => $progComp->id, 'version_code' => '2026.1', 'status' => 'published']);
    $verDraft = CurriculumVersion::create(['program_id' => $progComp->id, 'version_code' => '2026.2', 'status' => 'draft']);

    $period = CurriculumPeriod::create(['curriculum_version_id' => $verPub->id, 'name' => 'P1', 'sort_order' => 1, 'duration_weeks' => 12]);
    $comp = Competency::create(['name' => 'Coding Web', 'department' => 'computer', 'is_active' => true]);
    $subject = CurriculumSubject::create(['curriculum_period_id' => $period->id, 'competency_id' => $comp->id, 'name' => 'Web Dasar', 'sessions_per_week' => 2, 'sort_order' => 1]);

    $roomComp = Room::factory()->create(['name' => 'Lab 1', 'department' => 'computer', 'capacity' => 30, 'status' => 'available']);
    $roomMaint = Room::factory()->create(['name' => 'Lab 2', 'department' => 'computer', 'capacity' => 30, 'status' => 'maintenance']);
    $roomEng = Room::factory()->create(['name' => 'Ruang 1', 'department' => 'speakout', 'capacity' => 20, 'status' => 'available']);

    $facAc = Facility::create(['code' => 'FAC-AC', 'name' => 'AC', 'category' => 'general', 'is_active' => true]);
    $roomComp->facilities()->attach($facAc->id, ['quantity' => 2, 'condition' => 'good']);

    $inst = Instructor::factory()->create(['name' => 'Budi', 'department' => 'computer', 'status' => 'active']);
    InstructorSkill::create(['instructor_id' => $inst->id, 'competency_id' => $comp->id, 'skill' => 'Coding Web', 'level' => 'advanced']);
    InstructorAvailability::create(['instructor_id' => $inst->id, 'day' => 'Senin', 'start_time' => '08:00', 'end_time' => '12:00']);

    // 2. Query Read Contracts: Department Filtering & Status Checks
    $availableCompRooms = Room::query()
        ->where('department', 'computer')
        ->where('status', 'available')
        ->where('capacity', '>=', 20)
        ->get();

    expect($availableCompRooms)->toHaveCount(1)
        ->and($availableCompRooms->first()->id)->toBe($roomComp->id);

    // Hanya versi published yang boleh dijadwalkan
    $publishedVersions = CurriculumVersion::query()
        ->where('program_id', $progComp->id)
        ->where('status', 'published')
        ->get();

    expect($publishedVersions)->toHaveCount(1)
        ->and($publishedVersions->first()->id)->toBe($verPub->id);

    // Instruktur yang memenuhi kualifikasi
    $qualifiedInstructors = Instructor::query()
        ->where('department', 'computer')
        ->where('status', 'active')
        ->whereHas('skills', fn ($q) => $q->where('competency_id', $comp->id))
        ->get();

    expect($qualifiedInstructors)->toHaveCount(1)
        ->and($qualifiedInstructors->first()->id)->toBe($inst->id);
});

test('d4_b shared scheduling draft validation contract rules enforce both ai assistant and manual input structure', function () {
    $rules = [
        'input_mode' => 'required|in:ai_assistant,manual_input',
        'department' => 'required|in:computer,speakout',
        'class_kind' => 'required|in:regular,private',
        'student_count' => 'required|integer|min:1|max:100',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'total_meetings' => 'required|integer|min:1|max:100',
        'session_duration_minutes' => 'required|integer|in:60,90,120,180',
        'frequency_per_week' => 'required|integer|min:1|max:7',
        'preferred_weekdays' => 'required|array|min:1',
        'preferred_weekdays.*' => 'string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
        'preferred_time_window.start' => 'required|date_format:H:i',
        'preferred_time_window.end' => 'required|date_format:H:i|after:preferred_time_window.start',
    ];

    // Payload AI Assistant Valid
    $aiPayload = [
        'input_mode' => 'ai_assistant',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 20,
        'start_date' => '2026-11-23',
        'end_date' => '2027-02-15',
        'total_meetings' => 24,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 2,
        'preferred_weekdays' => ['Senin', 'Rabu'],
        'preferred_time_window' => ['start' => '08:00', 'end' => '12:00'],
    ];

    $validatorAi = Validator::make($aiPayload, $rules);
    expect($validatorAi->passes())->toBeTrue();

    // Payload Invalid: Durasi tidak diizinkan & tanggal masa lalu
    $invalidPayload = [
        'input_mode' => 'manual_input',
        'department' => 'invalid_dept',
        'class_kind' => 'regular',
        'student_count' => 0, // Min 1
        'start_date' => '2026-11-23',
        'end_date' => '2026-11-20', // Harus after start_date
        'total_meetings' => 24,
        'session_duration_minutes' => 45, // Harus 60,90,120,180
        'frequency_per_week' => 2,
        'preferred_weekdays' => [], // Min 1
        'preferred_time_window' => ['start' => '12:00', 'end' => '08:00'], // end harus after start
    ];

    $validatorInvalid = Validator::make($invalidPayload, $rules);
    expect($validatorInvalid->fails())->toBeTrue()
        ->and($validatorInvalid->errors()->keys())->toContain(
            'department',
            'student_count',
            'end_date',
            'session_duration_minutes',
            'preferred_weekdays',
            'preferred_time_window.end'
        );
});

test('d4_c proposal lifecycle and status matrix adheres to non-blocking pending rule', function () {
    $aoUser = User::factory()->create(['role' => 'ao']);

    $proposal = ScheduleProposal::create([
        'requested_by' => $aoUser->id,
        'title' => 'Proposal Pending D4',
        'input_mode' => 'ai_assistant',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 15,
        'start_date' => '2026-11-23',
        'end_date' => '2027-02-15',
        'total_meetings' => 24,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 2,
        'status' => 'pending',
    ]);

    // Proposal berstatus pending TIDAK membuat reservasi di schedules
    expect(Schedule::count())->toBe(0)
        ->and($proposal->status)->toBe('pending');

    // Query pengecekan bentrok jadwal resmi HANYA memeriksa status scheduled dan rescheduled
    $blockingSchedulesCount = Schedule::whereIn('status', ['scheduled', 'rescheduled'])->count();
    expect($blockingSchedulesCount)->toBe(0);
});

test('d4_d proposal options payload structure preserves meeting sequences and composite integrity', function () {
    $aoUser = User::factory()->create(['role' => 'ao']);

    $proposal = ScheduleProposal::create([
        'requested_by' => $aoUser->id,
        'title' => 'Proposal Options D4',
        'input_mode' => 'ai_assistant',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 15,
        'start_date' => '2026-11-23',
        'end_date' => '2027-02-15',
        'total_meetings' => 2,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 2,
        'status' => 'pending',
    ]);

    $payload = [
        [
            'meeting_sequence' => 1,
            'date' => '2026-11-23',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'room_id' => 1,
            'instructor_id' => 1,
        ],
        [
            'meeting_sequence' => 2,
            'date' => '2026-11-25',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'room_id' => 1,
            'instructor_id' => 1,
        ],
    ];

    $option = ScheduleProposalOption::create([
        'proposal_id' => $proposal->id,
        'option_label' => 'A',
        'proposed_schedules_payload' => $payload,
        'conflicts_count' => 0,
        'score' => 95,
    ]);

    $proposal->selectOption($option);

    expect($proposal->fresh()->selected_option_id)->toBe($option->id)
        ->and($option->proposed_schedules_payload)->toHaveCount(2)
        ->and($option->proposed_schedules_payload[0]['meeting_sequence'])->toBe(1)
        ->and($option->proposed_schedules_payload[1]['meeting_sequence'])->toBe(2);
});
