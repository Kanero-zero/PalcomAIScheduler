<?php

use App\Models\Competency;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorLeave;
use App\Models\InstructorSkill;
use App\Models\Room;
use App\Models\Schedule;
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

function fakeVerifierResponse(array $additionalUpdates = [], ?string $materialIntent = null): array
{
    return fakeAgentResponse([
        'additional_updates' => $additionalUpdates,
        'material_intent' => $materialIntent,
    ]);
}

/** @return array{competency:Competency,instructor:Instructor,room:Room} */
function agentResources(string $competencyName = 'Web Programming'): array
{
    $competency = Competency::factory()->create([
        'name' => $competencyName,
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
        'skill' => $competencyName,
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

function agentRecommendationResponse(array $resources, array $slots, array $recommendedPlan = []): array
{
    return fakeAgentResponse([
        'assistant_message' => 'Saya sudah memeriksa data operasional dan menemukan opsi.',
        'action' => 'present_options',
        'recommendation_requested' => true,
        'recommendations' => [[
            'option_label' => 'A',
            'score' => 92,
            'reason' => 'Resource dan slot sesuai data operasional.',
            'recommended_plan' => $recommendedPlan,
            'proposed_schedules_payload' => array_map(fn (array $slot): array => array_merge([
                'instructor_id' => $resources['instructor']->id,
                'room_id' => $resources['room']->id,
            ], $slot), $slots),
        ]],
    ]);
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
        ->push(fakeVerifierResponse())
        ->push(fakeAgentResponse(['state_updates' => ['class_kind' => 'regular']]))
        ->push(fakeVerifierResponse())
        ->push(fakeAgentResponse(['state_updates' => []]))
        ->push(fakeVerifierResponse(['start_date' => '2027-01-12', 'preferred_weekdays' => ['Selasa']]));
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

test('first turn operational context uses the latest student count and period', function () {
    $resources = agentResources('Microsoft Word');
    $smallRoom = Room::factory()->create([
        'name' => 'Lab Kapasitas Sepuluh', 'department' => 'computer', 'capacity' => 10, 'status' => 'available',
    ]);
    $official = Schedule::factory()->create([
        'course_class_id' => CourseClass::factory(),
        'instructor_id' => $resources['instructor']->id,
        'room_id' => $resources['room']->id,
        'date' => '2027-02-01',
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'status' => 'scheduled',
    ]);
    Http::preventStrayRequests();
    Http::fakeSequence('https://generativelanguage.googleapis.com/*')
        ->push(fakeAgentResponse([
            'state_updates' => [
                'title' => 'Microsoft Word', 'class_kind' => 'regular', 'student_count' => 12,
                'start_date' => '2027-01-10', 'end_date' => '2027-11-10', 'frequency_per_week' => 1,
            ],
            'recommendation_requested' => true,
            'action' => 'recommend_schedule',
        ]))
        ->push(fakeVerifierResponse())
        ->push(fakeAgentResponse(['assistant_message' => 'Belum ada opsi.', 'recommendations' => []]));

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'Buatkan jadwal Microsoft Word untuk 12 siswa dari 10 Januari sampai 10 November 2027, carikan yang terbaik.',
        ])->assertOk()->assertJsonPath('agent.state.student_count', 12);

    $recorded = Http::recorded();
    $recommendContext = $recorded[2][0]->data()['contents'][0]['parts'][0]['text'];
    expect($recommendContext)
        ->toContain('"phase":"recommend"', '"student_count":12', '"start_date":"2027-01-10"', '"end_date":"2027-11-10"')
        ->toContain('"id":'.$resources['room']->id)
        ->toContain('"id":'.$official->id)
        ->not->toContain('"id":'.$smallRoom->id.',"name":"Lab Kapasitas Sepuluh"');
});

test('Microsoft Word resolves to competency one and computer without department clarification', function () {
    $resources = agentResources('Microsoft Word');
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([
        'state_updates' => ['title' => 'Microsoft Word'],
        'assistant_message' => 'Apakah bidang Computer atau SpeakOut?',
        'clarification' => ['field' => 'department', 'question' => 'Pilih department.', 'choices' => ['Computer', 'SpeakOut']],
    ]))]);

    $response = $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), ['message' => 'Saya ingin kelas Microsoft Word'])
        ->assertOk()
        ->assertJsonPath('agent.state.required_competency_ids', [$resources['competency']->id])
        ->assertJsonPath('agent.state.department', 'computer')
        ->assertJsonPath('agent.clarification', null);

    expect($resources['competency']->id)->toBe(1)
        ->and(mb_strtolower($response->json('agent.assistant_message')))->not->toContain('computer atau speakout');
});

test('phase one verifier recovers explicit facts omitted by the first Gemini pass', function () {
    $resources = agentResources('Microsoft Word');
    Http::preventStrayRequests();
    Http::fakeSequence('https://generativelanguage.googleapis.com/*')
        ->push(fakeAgentResponse([
            'state_updates' => ['start_date' => '2026-11-10', 'end_date' => '2027-11-10'],
            'recommendation_requested' => true,
        ]))
        ->push(fakeVerifierResponse([
            'class_kind' => 'regular',
            'student_count' => 12,
            'frequency_per_week' => 1,
        ], 'word'))
        ->push(fakeAgentResponse(['assistant_message' => '', 'recommendations' => []]));

    $response = $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'buatkan saya jadwal untuk kelas 1 tahun reguler materi word, jumlah 12 orang, mulai 10 november 2026 - 10 november 2027, pertemuan 1x/minggu',
        ])->assertOk()
        ->assertJsonPath('agent.state.title', 'Microsoft Word')
        ->assertJsonPath('agent.state.department', 'computer')
        ->assertJsonPath('agent.state.class_kind', 'regular')
        ->assertJsonPath('agent.state.student_count', 12)
        ->assertJsonPath('agent.state.start_date', '2026-11-10')
        ->assertJsonPath('agent.state.end_date', '2027-11-10')
        ->assertJsonPath('agent.state.frequency_per_week', 1)
        ->assertJsonPath('agent.state.required_competency_ids', [$resources['competency']->id])
        ->assertJsonPath('agent.state_updates.class_kind', 'regular')
        ->assertJsonPath('agent.state_updates.student_count', 12)
        ->assertJsonPath('agent.state_updates.frequency_per_week', 1);

    expect(mb_strtolower($response->json('agent.assistant_message')))
        ->not->toContain('jumlah siswa diperlukan');
});

test('phase one structured output requests every supported field and a verifier schema', function () {
    agentResources('Microsoft Word');
    Http::preventStrayRequests();
    Http::fakeSequence('https://generativelanguage.googleapis.com/*')
        ->push(fakeAgentResponse(['state_updates' => []]))
        ->push(fakeVerifierResponse(['student_count' => 12]));

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), ['message' => 'pesertanya ada 12'])
        ->assertOk()
        ->assertJsonPath('agent.state.student_count', 12);

    $recorded = Http::recorded();
    $understandRequest = $recorded[0][0];
    $verifierRequest = $recorded[1][0];
    $understandProperties = $understandRequest->data()['generationConfig']['responseSchema']['properties']['state_updates']['properties'];
    $verifierProperties = $verifierRequest->data()['generationConfig']['responseSchema']['properties'];
    $understandPrompt = $understandRequest->data()['contents'][0]['parts'][0]['text'];

    expect(array_keys($understandProperties))->toEqual([
        'title', 'class_kind', 'student_count', 'start_date', 'end_date', 'total_meetings',
        'frequency_per_week', 'session_duration_minutes', 'preferred_weekdays',
        'preferred_time_window', 'required_facility_codes',
    ])->and($verifierProperties)->toHaveKeys(['additional_updates', 'material_intent'])
        ->and($understandPrompt)->toContain(
            'periksa satu per satu seluruh field yang didukung',
            'field tersebut WAJIB masuk state_updates',
        );
});

test('phase one verifier handles natural language variants without a backend phrase parser', function (
    string $message,
    array $additionalUpdates,
    ?string $materialIntent,
    string $statePath,
    mixed $expected,
) {
    agentResources('Microsoft Word');
    Http::preventStrayRequests();
    Http::fakeSequence('https://generativelanguage.googleapis.com/*')
        ->push(fakeAgentResponse(['state_updates' => []]))
        ->push(fakeVerifierResponse($additionalUpdates, $materialIntent));

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), ['message' => $message])
        ->assertOk()
        ->assertJsonPath($statePath, $expected);
})->with([
    'pesertanya ada 12' => ['pesertanya ada 12', ['student_count' => 12], null, 'agent.state.student_count', 12],
    'untuk dua belas siswa' => ['untuk dua belas siswa', ['student_count' => 12], null, 'agent.state.student_count', 12],
    'kelas reguler' => ['kelas reguler', ['class_kind' => 'regular'], null, 'agent.state.class_kind', 'regular'],
    'seminggu sekali' => ['seminggu sekali', ['frequency_per_week' => 1], null, 'agent.state.frequency_per_week', 1],
    'satu kali setiap minggu' => ['satu kali setiap minggu', ['frequency_per_week' => 1], null, 'agent.state.frequency_per_week', 1],
    'mulai tanggal 10 November' => ['mulai tanggal 10 November 2026', ['start_date' => '2026-11-10'], null, 'agent.state.start_date', '2026-11-10'],
    'Word' => ['Word', [], 'word', 'agent.state.title', 'Microsoft Word'],
    'Microsoft Word' => ['Microsoft Word', [], 'Microsoft Word', 'agent.state.title', 'Microsoft Word'],
]);

test('latest explicit correction wins while prior state persists across turns', function () {
    agentResources('Microsoft Word');
    Http::preventStrayRequests();
    Http::fakeSequence('https://generativelanguage.googleapis.com/*')
        ->push(fakeAgentResponse(['state_updates' => []]))
        ->push(fakeVerifierResponse(['student_count' => 12]))
        ->push(fakeAgentResponse(['state_updates' => ['student_count' => 12]]))
        ->push(fakeVerifierResponse(['student_count' => 15]))
        ->push(fakeAgentResponse(['state_updates' => ['class_kind' => 'regular']]))
        ->push(fakeVerifierResponse());
    $actor = User::factory()->create(['role' => 'ao']);

    $this->actingAs($actor)->postJson(route('ai-scheduler.v2-agent'), [
        'message' => '12 siswa',
        'current_state' => ['start_date' => '2026-11-10'],
    ])->assertOk()->assertJsonPath('agent.state.student_count', 12);

    $this->actingAs($actor)->postJson(route('ai-scheduler.v2-agent'), [
        'message' => 'ubah jadi 15 orang',
    ])->assertOk()
        ->assertJsonPath('agent.state.student_count', 15)
        ->assertJsonPath('agent.state.start_date', '2026-11-10');

    $this->actingAs($actor)->postJson(route('ai-scheduler.v2-agent'), [
        'message' => 'kelas reguler',
    ])->assertOk()
        ->assertJsonPath('agent.state.student_count', 15)
        ->assertJsonPath('agent.state.start_date', '2026-11-10')
        ->assertJsonPath('agent.state.class_kind', 'regular');
});

test('delegated scheduling can fill a recommended plan when manual draft fields are absent', function () {
    $resources = agentResources('Microsoft Word');
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(agentRecommendationResponse($resources, [
        ['meeting_sequence' => 1, 'date' => '2027-01-04', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
        ['meeting_sequence' => 2, 'date' => '2027-01-11', 'start_time' => '16:00:00', 'end_time' => '18:00:00'],
    ], ['class_kind' => 'regular']))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'Carikan jadwal terbaik untuk Microsoft Word, hari dan jam bebas.',
            'current_state' => [
                'title' => 'Microsoft Word', 'student_count' => 12,
                'start_date' => '2027-01-01', 'end_date' => '2027-01-31', 'frequency_per_week' => 1,
            ],
        ])->assertOk()
        ->assertJsonPath('agent.action', 'present_options')
        ->assertJsonPath('agent.state.preferred_weekdays', null)
        ->assertJsonPath('agent.recommendations.0.recommended_plan.class_kind', 'regular')
        ->assertJsonPath('agent.recommendations.0.recommended_plan.total_meetings', 2)
        ->assertJsonPath('agent.recommendations.0.recommended_plan.session_duration_minutes', 120)
        ->assertJsonPath('agent.recommendations.0.recommended_plan.preferred_weekdays', ['Senin'])
        ->assertJsonPath('agent.recommendations.0.recommended_plan.preferred_time_window.start', '16:00:00');
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

test('recommendation is rejected when instructor availability does not cover the slot', function () {
    $resources = agentResources();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(agentRecommendationResponse($resources, [[
        'meeting_sequence' => 1, 'date' => '2027-01-04', 'start_time' => '15:00:00', 'end_time' => '17:00:00',
    ]], ['class_kind' => 'regular']))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'Carikan jadwal Web Programming terbaik.',
            'current_state' => [
                'title' => 'Web Programming', 'student_count' => 15,
                'start_date' => '2027-01-01', 'end_date' => '2027-01-31', 'frequency_per_week' => 1,
            ],
        ])->assertOk()->assertJsonPath('agent.recommendations', []);
});

test('official schedule conflict blocks an AI recommendation', function () {
    $resources = agentResources();
    Schedule::factory()->create([
        'course_class_id' => CourseClass::factory(),
        'instructor_id' => $resources['instructor']->id,
        'room_id' => $resources['room']->id,
        'date' => '2027-01-04',
        'start_time' => '16:30:00',
        'end_time' => '17:30:00',
        'status' => 'scheduled',
    ]);
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(agentRecommendationResponse($resources, [[
        'meeting_sequence' => 1, 'date' => '2027-01-04', 'start_time' => '16:00:00', 'end_time' => '18:00:00',
    ]]))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'Carikan jadwal Web Programming terbaik.',
            'current_state' => array_merge(completeAgentState(), ['total_meetings' => 1, 'frequency_per_week' => 1, 'preferred_weekdays' => ['Senin']]),
        ])->assertOk()->assertJsonPath('agent.recommendations', []);
});

test('instructor leave conflict blocks an AI recommendation', function () {
    $resources = agentResources();
    InstructorLeave::factory()->create([
        'instructor_id' => $resources['instructor']->id,
        'date' => '2027-01-04',
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'status' => 'approved',
    ]);
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(agentRecommendationResponse($resources, [[
        'meeting_sequence' => 1, 'date' => '2027-01-04', 'start_time' => '16:00:00', 'end_time' => '18:00:00',
    ]]))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'Carikan jadwal Web Programming terbaik.',
            'current_state' => array_merge(completeAgentState(), ['total_meetings' => 1, 'frequency_per_week' => 1, 'preferred_weekdays' => ['Senin']]),
        ])->assertOk()->assertJsonPath('agent.recommendations', []);
});

test('room capacity and department remove rooms from the AI allowlist', function () {
    $resources = agentResources();
    $smallRoom = Room::factory()->create([
        'name' => 'Lab Kecil', 'department' => 'computer', 'capacity' => 5, 'status' => 'available',
    ]);
    $wrongDepartmentRoom = Room::factory()->create([
        'name' => 'Ruang SpeakOut', 'department' => 'speakout', 'capacity' => 30, 'status' => 'available',
    ]);
    $baseSlot = [
        'meeting_sequence' => 1, 'date' => '2027-01-04', 'start_time' => '16:00:00', 'end_time' => '18:00:00',
        'instructor_id' => $resources['instructor']->id,
    ];
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([
        'recommendation_requested' => true,
        'action' => 'present_options',
        'recommendations' => [
            ['option_label' => 'A', 'recommended_plan' => [], 'proposed_schedules_payload' => [array_merge($baseSlot, ['room_id' => $smallRoom->id])]],
            ['option_label' => 'B', 'recommended_plan' => [], 'proposed_schedules_payload' => [array_merge($baseSlot, ['room_id' => $wrongDepartmentRoom->id])]],
        ],
    ]))]);

    $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), [
            'message' => 'Carikan jadwal Web Programming terbaik.',
            'current_state' => array_merge(completeAgentState(), ['total_meetings' => 1, 'frequency_per_week' => 1, 'preferred_weekdays' => ['Senin']]),
        ])->assertOk()->assertJsonPath('agent.recommendations', []);
});

test('agent never delegates database checking back to the user', function () {
    agentResources();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(fakeAgentResponse([
        'state_updates' => ['title' => 'Web Programming'],
        'assistant_message' => 'Mohon pastikan ketersediaan instruktur dan ruangan telah diatur di sistem.',
    ]))]);

    $response = $this->actingAs(User::factory()->create(['role' => 'ao']))
        ->postJson(route('ai-scheduler.v2-agent'), ['message' => 'Saya ingin kelas Web Programming'])
        ->assertOk();

    expect(mb_strtolower($response->json('agent.assistant_message')))
        ->not->toContain('mohon pastikan', 'silakan periksa')
        ->toContain('schedora akan membaca');
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
