<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function v2InputDraft(): array
{
    return [
        'input_mode' => 'ai_assistant',
        'title' => 'Microsoft Excel',
        'department' => 'computer',
        'class_kind' => 'regular',
        'student_count' => 10,
        'start_date' => '2026-11-23',
        'end_date' => '2027-02-15',
        'total_meetings' => 8,
        'session_duration_minutes' => 120,
        'frequency_per_week' => 2,
        'preferred_weekdays' => ['Senin', 'Rabu'],
        'preferred_time_window' => ['start' => '16:00', 'end' => '18:00'],
        'required_competency_ids' => [],
        'required_facility_codes' => [],
    ];
}

test('v2 Gemini endpoints reject unauthenticated users before calling an API', function () {
    Http::fake();
    $this->postJson(route('ai-scheduler.v2-interpret'), ['prompt' => 'Buat jadwal kursus komputer untuk 10 siswa'])->assertUnauthorized();
    Http::assertNothingSent();
});

test('v2 Gemini interpretation is real HTTP and drops untrusted resource IDs', function () {
    config()->set('services.gemini.api_key', 'unit-test-key');
    config()->set('services.gemini.model', 'gemini-2.5-flash');
    Http::fake(['*generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode([
            'fields' => [
                'title' => 'Microsoft Excel', 'student_count' => 15,
                'preferred_weekdays' => ['Senin', 'Rabu'],
                'room_id' => 999, 'requested_by' => 5, 'status' => 'approved',
            ],
            'missing_fields' => ['start_date'],
            'summary' => 'Silakan lengkapi tanggal mulai.',
        ])]]]]],
    ], 200)]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('ai-scheduler.v2-interpret'), ['prompt' => 'Buat kelas Excel untuk 15 siswa setiap Senin dan Rabu'])
        ->assertOk()
        ->assertJsonPath('source', 'gemini')
        ->assertJsonPath('ai.fields.student_count', 15)
        ->assertJsonMissingPath('ai.fields.room_id')
        ->assertJsonMissingPath('ai.fields.status')
        ->assertJsonMissingPath('ai.fields.requested_by');
    Http::assertSentCount(1);
});

test('v2 Gemini returns explicit unavailable status without key and never calls provider', function () {
    config()->set('services.gemini.api_key', '');
    Http::fake();
    $this->actingAs(User::factory()->create())
        ->postJson(route('ai-scheduler.v2-interpret'), ['prompt' => 'Buat jadwal kursus komputer untuk 10 siswa'])
        ->assertStatus(503)
        ->assertJsonPath('source', 'unavailable');
    Http::assertNothingSent();
});

test('v2 preview uses G3 and does not create any official schedule or proposal', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('ai-scheduler.v2-recommendations'), ['draft' => v2InputDraft()])
        ->assertOk()->assertJsonStructure(['alternatives_count', 'options', 'reasons']);
    $this->assertDatabaseCount('schedules', 0);
    $this->assertDatabaseCount('schedule_proposals', 0);
});

test('v2 submit rejects non AO before creating a proposal', function () {
    $this->actingAs(User::factory()->create(['role' => 'baak']))
        ->postJson(route('ai-scheduler.v2-submit'), [
            'draft' => v2InputDraft(),
            'option_label' => 'A',
            'preview_token' => str_repeat('a', 64),
        ])->assertForbidden();
    $this->assertDatabaseCount('schedule_proposals', 0);
});
