<?php

use App\Models\Competency;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorSkill;
use App\Models\Room;
use App\Models\User;
use App\Services\Scheduling\CatalogContextResolver;
use App\Services\Scheduling\DeterministicSchedulingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeAgentResponse(array $payload): array
{
    return [
        'candidates' => [['content' => ['parts' => [['text' => json_encode(array_merge([
            'intent' => 'create_schedule',
            'state_updates' => [],
            'cleared_fields' => [],
            'inferences' => [],
            'assistant_message' => 'Baik, saya memperbarui kebutuhan kelas Anda.',
            'action' => 'continue_conversation',
            'recommendations' => [],
            'clarification' => null,
        ], $payload), JSON_THROW_ON_ERROR)]]]]],
    ];
}

/** @return array{competency:Competency,instructor:Instructor,room:Room} */
function agentResources(): array
{
    $competency = Competency::factory()->create([
        'name' => 'Web Programming',
        'department' => 'computer',
        'is_active' => true,
    ]);
    $instructor = Instructor::factory()->create([
        'name' => 'Instruktur Web Nyata',
        'department' => 'computer',
        'status' => 'active',
    ]);
    InstructorSkill::factory()->create([
        'instructor_id' => $instructor->id,
        'competency_id' => $competency->id,
        'skill' => 'Web Programming',
        'level' => 'advanced',
    ]);
    foreach (['Senin', 'Rabu'] as $day) {
        InstructorAvailability::factory()->create([
            'instructor_id' => $instructor->id,
            'day' => $day,
            'start_time' => '16:00:00',
            'end_time' => '18:00:00',
        ]);
    }
    $room = Room::factory()->create([
        'name' => 'Lab Web Nyata',
        'department' => 'computer',
        'capacity' => 30,
        'status' => 'available',
    ]);

    return compact('competency', 'instructor', 'room');
}

function completeAgentState(): array
{
    return [
        'title' => 'Web Programming',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 15,
        'start_date' => '2027-01-04',
        'end_date' => '2027-01-31',
        'total_meetings' => 2,
        'frequency_per_week' => 2,
        'session_duration_minutes' => 120,
        'preferred_weekdays' => ['Senin', 'Rabu'],
        'preferred_time_window' => ['start' => '16:00', 'end' => '18:00'],
        'required_facility_codes' => [],
    ];
}

function agentRecommendations(array $resources): array
{
    return [[
        'option_label' => 'A',
        'score' => 94,
        'reason' => 'Instruktur memiliki kompetensi dan slot tersedia.',
        'proposed_schedules_payload' => [
            ['meeting_sequence' => 1, 'date' => '2027-01-04', 'start_time' => '16:00:00', 'end_time' => '18:00:00', 'instructor_id' => $resources['instructor']->id, 'room_id' => $resources['room']->id],
            ['meeting_sequence' => 2, 'date' => '2027-01-06', 'start_time' => '16:00:00', 'end_time' => '18:00:00', 'instructor_id' => $resources['instructor']->id, 'room_id' => $resources['room']->id],
        ],
    ]];
}

beforeEach(function () {
    config()->set('services.gemini.api_key', 'agent-test-key');
    config()->set('services.gemini.model', 'gemini-2.5-flash');
});

test('Web Programming resolves to its database competency and computer department', function () {
    $resources = agentResources();

    $result = app(CatalogContextResolver::class)->resolveMaterial('Buat kelas Web Programming untuk 15 siswa');

    expect($result['status'])->toBe('unique')
        ->and($result['competency']['id'])->toBe($resources['competency']->id)
        ->and($result['competency']['department'])->toBe('computer');
});

test('agent applies unique catalog inference and never asks for department', function () {
    $resources = agentResources();
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([
        'state_updates' => ['title' => 'Web Programming', 'student_count' => 15],
        'assistant_message' => 'Kelas ini bidang Komputer atau SpeakOut?',
        'action' => 'request_clarification',
        'clarification' => ['field' => 'department', 'question' => 'Komputer atau SpeakOut?', 'choices' => ['Komputer', 'SpeakOut']],
    ]))]);

    $response = $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), ['message' => 'Buat kelas Web Programming untuk 15 siswa'])
        ->assertOk()
        ->assertJsonPath('agent.state.department', 'computer')
        ->assertJsonPath('agent.state.required_competency_ids.0', $resources['competency']->id)
        ->assertJsonPath('agent.clarification', null);

    expect(mb_strtolower($response->json('agent.assistant_message')))
        ->not->toContain('komputer atau speakout');
});

test('agent rejects invented instructor and room identifiers from model recommendations', function () {
    agentResources();
    $invented = ['instructor' => new Instructor(['id' => 999998]), 'room' => new Room(['id' => 999999])];
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([
        'assistant_message' => 'Saya menemukan opsi.',
        'action' => 'present_options',
        'recommendations' => agentRecommendations($invented),
    ]))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'Buat rekomendasi Web Programming.',
            'current_state' => completeAgentState(),
        ])->assertOk()
        ->assertJsonPath('agent.action', 'continue_conversation')
        ->assertJsonPath('agent.recommendations', []);
});

test('conversation state persists across turns and a later correction replaces the old value', function () {
    agentResources();
    Http::preventStrayRequests();
    Http::fakeSequence('https://generativelanguage.googleapis.com/*')
        ->push(fakeAgentResponse(['state_updates' => [
            'title' => 'Web Programming', 'student_count' => 15,
            'start_date' => '2027-01-04', 'preferred_weekdays' => ['Senin'],
        ]]))
        ->push(fakeAgentResponse(['state_updates' => ['class_kind' => 'regular']]))
        ->push(fakeAgentResponse(['state_updates' => ['start_date' => '2027-01-12', 'preferred_weekdays' => ['Selasa']]]));
    $actor = User::factory()->create(['role' => 'ao']);

    $this->actingAs($actor)->postJson(route('ai-scheduler.v2-agent'), [
        'message' => 'Web Programming untuk 15 siswa mulai 4 Januari 2027 setiap Senin.',
    ])->assertOk();
    $this->actingAs($actor)->postJson(route('ai-scheduler.v2-agent'), [
        'message' => 'Kelas reguler saja.',
    ])->assertOk()
        ->assertJsonPath('agent.state.student_count', 15)
        ->assertJsonPath('agent.state.start_date', '2027-01-04')
        ->assertJsonPath('agent.state.preferred_weekdays', ['Senin']);
    $this->actingAs($actor)->postJson(route('ai-scheduler.v2-agent'), [
        'message' => 'Ubah mulai 12 Januari dan kalau Senin tidak bisa, coba Selasa.',
    ])->assertOk()
        ->assertJsonPath('agent.state.student_count', 15)
        ->assertJsonPath('agent.state.start_date', '2027-01-12')
        ->assertJsonPath('agent.state.preferred_weekdays', ['Selasa']);
});

test('ambiguous material requests catalog clarification', function () {
    Competency::factory()->create(['name' => 'Web Programming Basic', 'department' => 'computer', 'is_active' => true]);
    Competency::factory()->create(['name' => 'Web Programming Advanced', 'department' => 'computer', 'is_active' => true]);
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([]))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), ['message' => 'Saya ingin kelas Web Programming'])
        ->assertOk()
        ->assertJsonPath('agent.action', 'request_clarification')
        ->assertJsonPath('agent.clarification.field', 'title')
        ->assertJsonCount(2, 'agent.clarification.choices');
});

test('agent recommendations only expose real qualified instructors and available rooms', function () {
    $resources = agentResources();
    Instructor::factory()->create(['name' => 'Instruktur Tanpa Kompetensi', 'department' => 'computer', 'status' => 'active']);
    Room::factory()->create(['name' => 'Ruangan Terlalu Kecil', 'department' => 'computer', 'capacity' => 5, 'status' => 'available']);
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([
        'assistant_message' => 'Saya menemukan opsi terbaik dari data operasional.',
        'action' => 'present_options',
        'recommendations' => agentRecommendations($resources),
    ]))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'Tawarkan jadwal Web Programming terbaik.',
            'current_state' => completeAgentState(),
        ])->assertOk()
        ->assertJsonPath('agent.action', 'present_options')
        ->assertJsonPath('agent.recommendations.0.proposed_schedules_payload.0.instructor_id', $resources['instructor']->id)
        ->assertJsonPath('agent.recommendations.0.proposed_schedules_payload.0.instructor_name', 'Instruktur Web Nyata')
        ->assertJsonPath('agent.recommendations.0.proposed_schedules_payload.0.room_id', $resources['room']->id)
        ->assertJsonPath('agent.recommendations.0.proposed_schedules_payload.0.room_name', 'Lab Web Nyata')
        ->assertJsonPath('agent.recommendations.0.preview_token', fn ($token) => is_string($token) && mb_strlen($token) === 64);
});

test('AI mode does not invoke the deterministic scheduling engine', function () {
    agentResources();
    $engine = Mockery::mock(DeterministicSchedulingEngine::class);
    $engine->shouldNotReceive('generate');
    app()->instance(DeterministicSchedulingEngine::class, $engine);
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([
        'state_updates' => ['title' => 'Web Programming'],
    ]))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), ['message' => 'Buat kelas Web Programming'])
        ->assertOk();
});

test('Manual Input continues to invoke the deterministic scheduling engine', function () {
    $engine = Mockery::mock(DeterministicSchedulingEngine::class);
    $engine->shouldReceive('generate')->once()->andReturn([
        'alternatives_count' => 0, 'options' => [], 'reasons' => ['Tidak ada opsi.'], 'search_exhausted' => true,
    ]);
    app()->instance(DeterministicSchedulingEngine::class, $engine);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-recommendations'), ['draft' => array_merge(completeAgentState(), [
            'input_mode' => 'manual_input',
            'preferred_instructor_id' => null,
            'preferred_room_id' => null,
            'required_competency_ids' => [],
        ])])->assertOk()->assertJsonPath('alternatives_count', 0);
});

test('agent submission creates only a pending proposal and never an official schedule', function () {
    $resources = agentResources();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([
        'assistant_message' => 'Silakan pilih opsi berikut.',
        'action' => 'present_options',
        'recommendations' => agentRecommendations($resources),
    ]))]);
    $actor = User::factory()->create(['role' => 'ao']);
    $agentResponse = $this->actingAs($actor)->postJson(route('ai-scheduler.v2-agent'), [
        'message' => 'Buat rekomendasi Web Programming.',
        'current_state' => completeAgentState(),
    ])->assertOk();
    $token = $agentResponse->json('agent.recommendations.0.preview_token');

    $this->actingAs($actor)->postJson(route('ai-scheduler.v2-agent-submit'), [
        'option_label' => 'A', 'preview_token' => $token,
    ])->assertCreated()->assertJsonPath('status', 'pending')->assertJsonPath('is_official_reserved', false);

    $this->assertDatabaseHas('schedule_proposals', ['requested_by' => $actor->id, 'status' => 'pending', 'input_mode' => 'ai_assistant']);
    $this->assertDatabaseCount('schedules', 0);
    $this->assertDatabaseCount('schedule_approval_decisions', 0);
});
