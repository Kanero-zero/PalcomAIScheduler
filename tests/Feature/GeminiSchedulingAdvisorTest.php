<?php

use App\Livewire\AiScheduler;
use App\Livewire\InstructorLeaveForm;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Scheduling\DTOs\ScheduleEvaluation;
use App\Services\Scheduling\GeminiSchedulingAdvisor;
use App\Services\Scheduling\SchedulingEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Config::set('services.gemini.api_key', 'test-dummy-api-key');
    Config::set('services.gemini.model', 'gemini-3.5-flash-lite');

    $this->engine = app(SchedulingEngine::class);
    $this->advisor = app(GeminiSchedulingAdvisor::class);
    $this->user = User::first() ?? User::factory()->create();
});

test('it configures dummy api key in testing environment to allow all tests without real api key', function () {
    expect(config('services.gemini.api_key'))->toBe('test-dummy-api-key')
        ->and(config('services.gemini.model'))->toBe('gemini-3.5-flash-lite');

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Dummy test response.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => 2,
                                            'instructor_name' => 'Kanero',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Instruktur unggulan.',
                                            'confidence_score' => 95,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'modelVersion' => 'gemini-3.5-flash-lite',
        ], 200),
    ]);

    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    $advisor = new GeminiSchedulingAdvisor;
    $result = $advisor->enhanceEvaluation($this->engine->evaluateLeave($leave));

    expect($result->aiSummary)->not->toBeNull()
        ->and($result->aiSummary['status'])->toBe('success')
        ->and($result->aiSummary['model'])->toBe('gemini-3.5-flash-lite');
});

test('it successfully enhances scheduling evaluation with Gemini 3.5 Flash-Lite via mock API', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Kanero sangat direkomendasikan karena memiliki kompetensi tingkat Advanced dan beban kerja yang proporsional.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => 2,
                                            'instructor_name' => 'Kanero',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Sangat menguasai materi dan memiliki riwayat pengajaran yang sangat baik.',
                                            'confidence_score' => 96,
                                        ],
                                        [
                                            'instructor_id' => 5,
                                            'instructor_name' => 'Budi Santoso',
                                            'rank' => 2,
                                            'ai_reasoning' => 'Kompeten namun berada di level Intermediate.',
                                            'confidence_score' => 88,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'modelVersion' => 'gemini-3.5-flash-lite',
        ], 200),
    ]);

    $deterministicResult = $this->engine->evaluateLeave($leave);
    $enhancedResult = $this->advisor->enhanceEvaluation($deterministicResult);

    expect($enhancedResult->aiSummary)->not->toBeNull()
        ->and($enhancedResult->aiSummary['status'])->toBe('success')
        ->and($enhancedResult->aiSummary['is_ai_generated'])->toBeTrue()
        ->and($enhancedResult->aiSummary['model'])->toBe('gemini-3.5-flash-lite')
        ->and($enhancedResult->aiSummary['fallback_used'])->toBeFalse()
        ->and($enhancedResult->aiSummary['executive_summary'])->toContain('berhasil dianalisis');

    $excelEval = collect($enhancedResult->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');
    expect($excelEval)->not->toBeNull()
        ->and($excelEval->aiRecommendation)->not->toBeNull()
        ->and($excelEval->aiRecommendation['status'])->toBe('success')
        ->and($excelEval->aiRecommendation['is_ai_generated'])->toBeTrue()
        ->and($excelEval->aiRecommendation['model'])->toBe('gemini-3.5-flash-lite')
        ->and($excelEval->aiRecommendation['fallback_used'])->toBeFalse()
        ->and($excelEval->aiRecommendation['summary_explanation'])->toContain('Kanero sangat direkomendasikan')
        ->and($excelEval->aiRecommendation['rankings'])->toHaveCount(2)
        ->and($excelEval->aiRecommendation['rankings'][0]['instructor_name'])->toBe('Kanero')
        ->and($excelEval->aiRecommendation['rankings'][0]['confidence_score'])->toBe(96);
});

test('it sends API key via x-goog-api-key header and not in query parameters', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Pengujian keamanan header API key.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => 2,
                                            'rank' => 1,
                                            'ai_reasoning' => 'Valid.',
                                            'confidence_score' => 90,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $deterministicResult = $this->engine->evaluateLeave($leave);
    $this->advisor->enhanceEvaluation($deterministicResult);

    Http::assertSent(function (Request $request) {
        $hasHeader = $request->hasHeader('x-goog-api-key');
        $urlHasNoKeyParam = ! str_contains($request->url(), 'key=');

        return $hasHeader && $urlHasNoKeyParam;
    });
});

test('it strictly validates and sanitizes AI response (unique IDs, DB names, normalized sequential ranks, clamped scores)', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    // AI mencoba mengirim:
    // 1. ID 2 duplikat (dikirim 2 kali)
    // 2. Nama instruktur dipalsukan / halusinasi ("Hacker Coach" alih-alih "Kanero")
    // 3. Ranking duplikat (keduanya rank 1)
    // 4. Skor di luar batas (150 dan -20)
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Uji validasi ketat dan sanitasi.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => 2,
                                            'instructor_name' => 'Hacker Coach',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Kandidat pertama.',
                                            'confidence_score' => 150,
                                        ],
                                        [
                                            'instructor_id' => 2,
                                            'instructor_name' => 'Hacker Clone',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Kandidat duplikat.',
                                            'confidence_score' => 90,
                                        ],
                                        [
                                            'instructor_id' => 5,
                                            'instructor_name' => 'Budi Bukan Resmi',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Kandidat kedua.',
                                            'confidence_score' => -20,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $deterministicResult = $this->engine->evaluateLeave($leave);
    $enhancedResult = $this->advisor->enhanceEvaluation($deterministicResult);

    $excelEval = collect($enhancedResult->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');
    $rankings = $excelEval->aiRecommendation['rankings'];

    // 1. ID kandidat harus unik (hanya ID 2 dan ID 5)
    $candIds = array_column($rankings, 'instructor_id');
    expect($candIds)->toBe([2, 5]);

    // 2. Nama instruktur wajib diambil dari database / hasil deterministik
    expect($rankings[0]['instructor_name'])->toBe('Kanero')
        ->and($rankings[1]['instructor_name'])->toBe('Budi Santoso');

    // 3. Ranking tidak boleh duplikat dan dinormalisasi berurutan (1, 2)
    expect($rankings[0]['rank'])->toBe(1)
        ->and($rankings[1]['rank'])->toBe(2);

    // 4. Skor keyakinan dibatasi dalam rentang [0, 100]
    expect($rankings[0]['confidence_score'])->toBe(100)
        ->and($rankings[1]['confidence_score'])->toBe(0);
});

test('it sets status to success when all eligible schedules are successfully analyzed by Gemini', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();
    $deterministicResult = $this->engine->evaluateLeave($leave);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Sukses penuh.',
                                    'rankings' => [
                                        ['instructor_id' => 2, 'rank' => 1, 'confidence_score' => 95, 'ai_reasoning' => 'Cocok.'],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'modelVersion' => 'gemini-3.5-flash-lite',
        ], 200),
    ]);

    $result = $this->advisor->enhanceEvaluation($deterministicResult);
    expect($result->aiSummary['status'])->toBe('success')
        ->and($result->aiSummary['fallback_used'])->toBeFalse();
});

test('it sets status to partial when some schedules succeed and some fallback', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();
    $deterministicResult = $this->engine->evaluateLeave($leave);

    Http::fakeSequence('https://generativelanguage.googleapis.com/*')
        ->push([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Kelas pertama sukses.',
                                    'rankings' => [
                                        ['instructor_id' => 2, 'rank' => 1, 'confidence_score' => 90, 'ai_reasoning' => 'Bagus.'],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'modelVersion' => 'gemini-3.5-flash-lite',
        ], 200)
        ->push('Internal Server Error', 500);

    $result = $this->advisor->enhanceEvaluation($deterministicResult);
    expect($result->aiSummary['status'])->toBe('partial')
        ->and($result->aiSummary['fallback_used'])->toBeTrue()
        ->and($result->aiSummary['executive_summary'])->toContain('Analisis Sebagian (Partial)');
});

test('it sets status to fallback when all eligible schedules fail or API is down', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();
    $deterministicResult = $this->engine->evaluateLeave($leave);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response('Service Unavailable', 503),
    ]);

    $result = $this->advisor->enhanceEvaluation($deterministicResult);
    expect($result->aiSummary['status'])->toBe('fallback')
        ->and($result->aiSummary['fallback_used'])->toBeTrue()
        ->and($result->aiSummary['executive_summary'])->toContain('Mode Fallback Aktif');
});

test('it sets status to not_applicable when no eligible schedules or no valid candidates exist', function () {
    $rareClass = CourseClass::factory()->create(['subject' => 'Astrophysics Class']);
    $room = Room::factory()->create();
    $aloneInstructor = Instructor::factory()->create();
    Schedule::factory()->create([
        'instructor_id' => $aloneInstructor->id,
        'course_class_id' => $rareClass->id,
        'room_id' => $room->id,
        'date' => '2026-12-10',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
    ]);
    $aloneLeave = InstructorLeave::factory()->create([
        'instructor_id' => $aloneInstructor->id,
        'date' => '2026-12-10',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
    ]);

    Http::fake();
    $result = $this->engine->evaluateWithAi($aloneLeave);

    expect($result->aiSummary['status'])->toBe('not_applicable')
        ->and($result->aiSummary['fallback_used'])->toBeFalse()
        ->and($result->aiSummary['executive_summary'])->toContain('Tidak ada kandidat pengganti yang memenuhi syarat');
    Http::assertNothingSent();
});

test('it differentiates deterministic score from ai confidence score and keeps confidence_score null in fallback mode', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    // Mode Fallback: API gagal
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response('Server Error', 500),
    ]);

    $deterministicResult = $this->engine->evaluateLeave($leave);
    $fallbackResult = $this->advisor->enhanceEvaluation($deterministicResult);

    $excelEval = collect($fallbackResult->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');

    // Pada kandidat deterministik, skor engine adalah bilangan integer (bisa > 100)
    $deterministicCandidate = $excelEval->validCandidates[0];
    expect($deterministicCandidate->score)->toBeGreaterThan(0);

    // Pada fallback mode, AI confidence_score harus NULL dan tidak diisi skor deterministik
    $fallbackRankings = $excelEval->aiRecommendation['rankings'];
    expect($fallbackRankings)->not->toBeEmpty();
    foreach ($fallbackRankings as $ranking) {
        expect($ranking['confidence_score'])->toBeNull();
    }
});

test('it prevents cross-schedule conflict by not recommending the same instructor for overlapping classes', function () {
    $eval1 = new ScheduleEvaluation(
        scheduleId: 101,
        courseClassId: 1,
        className: 'Kelas Web A',
        subject: 'Web Design',
        date: '2026-12-15',
        startTime: '10:00:00',
        endTime: '12:00:00',
        roomEvaluation: null,
        status: 'resolved',
        candidates: [],
        validCandidates: [],
        disqualifiedCandidates: [],
        bestCandidate: null,
        summary: 'Rekomendasi awal.',
        warnings: [],
        aiRecommendation: [
            'status' => 'success',
            'is_ai_generated' => true,
            'model' => 'gemini-3.5-flash-lite',
            'best_candidate_id' => 2,
            'summary_explanation' => 'Instruktur Kanero adalah pilihan utama.',
            'rankings' => [
                ['instructor_id' => 2, 'instructor_name' => 'Kanero', 'rank' => 1, 'confidence_score' => 95],
                ['instructor_id' => 5, 'instructor_name' => 'Budi Santoso', 'rank' => 2, 'confidence_score' => 85],
            ],
            'fallback_used' => false,
            'fallback_reason' => null,
        ]
    );

    $eval2 = new ScheduleEvaluation(
        scheduleId: 102,
        courseClassId: 2,
        className: 'Kelas Web B',
        subject: 'Graphic Design',
        date: '2026-12-15',
        startTime: '11:00:00',
        endTime: '13:00:00',
        roomEvaluation: null,
        status: 'resolved',
        candidates: [],
        validCandidates: [],
        disqualifiedCandidates: [],
        bestCandidate: null,
        summary: 'Rekomendasi awal.',
        warnings: [],
        aiRecommendation: [
            'status' => 'success',
            'is_ai_generated' => true,
            'model' => 'gemini-3.5-flash-lite',
            'best_candidate_id' => 2,
            'summary_explanation' => 'Instruktur Kanero juga diprediksi cocok untuk kelas ini.',
            'rankings' => [
                ['instructor_id' => 2, 'instructor_name' => 'Kanero', 'rank' => 1, 'confidence_score' => 94],
                ['instructor_id' => 5, 'instructor_name' => 'Budi Santoso', 'rank' => 2, 'confidence_score' => 88],
            ],
            'fallback_used' => false,
            'fallback_reason' => null,
        ]
    );

    $resolved = $this->advisor->resolveCrossScheduleAiConflicts([$eval1, $eval2]);

    $rec1 = $resolved[0]->aiRecommendation;
    $rec2 = $resolved[1]->aiRecommendation;

    expect($rec1['best_candidate_id'])->toBe(2)
        ->and($rec2['best_candidate_id'])->toBe(5)
        ->and($rec2['best_candidate_id'])->not->toBe($rec1['best_candidate_id'])
        ->and($rec2['summary_explanation'])->toContain('Penyesuaian Konflik: Dialihkan ke Budi Santoso');
});

test('it sets best_candidate_id to null and warns if all ai candidates have cross-schedule time conflicts', function () {
    $eval1 = new ScheduleEvaluation(
        scheduleId: 201,
        courseClassId: 1,
        className: 'Kelas Pagi',
        subject: 'Web Design',
        date: '2026-12-15',
        startTime: '08:00:00',
        endTime: '10:00:00',
        roomEvaluation: null,
        status: 'resolved',
        candidates: [],
        validCandidates: [],
        disqualifiedCandidates: [],
        bestCandidate: null,
        summary: 'Rekomendasi awal.',
        warnings: [],
        aiRecommendation: [
            'status' => 'success',
            'is_ai_generated' => true,
            'model' => 'gemini-3.5-flash-lite',
            'best_candidate_id' => 2,
            'summary_explanation' => 'Kanero terpilih.',
            'rankings' => [
                ['instructor_id' => 2, 'instructor_name' => 'Kanero', 'rank' => 1, 'confidence_score' => 95],
            ],
            'fallback_used' => false,
            'fallback_reason' => null,
        ]
    );

    $eval2 = new ScheduleEvaluation(
        scheduleId: 202,
        courseClassId: 2,
        className: 'Kelas Tumpang Tindih',
        subject: 'Web Design',
        date: '2026-12-15',
        startTime: '09:00:00',
        endTime: '11:00:00',
        roomEvaluation: null,
        status: 'resolved',
        candidates: [],
        validCandidates: [],
        disqualifiedCandidates: [],
        bestCandidate: null,
        summary: 'Rekomendasi awal.',
        warnings: [],
        aiRecommendation: [
            'status' => 'success',
            'is_ai_generated' => true,
            'model' => 'gemini-3.5-flash-lite',
            'best_candidate_id' => 2,
            'summary_explanation' => 'Kanero juga menjadi satu-satunya kandidat di sini.',
            'rankings' => [
                ['instructor_id' => 2, 'instructor_name' => 'Kanero', 'rank' => 1, 'confidence_score' => 90],
            ],
            'fallback_used' => false,
            'fallback_reason' => null,
        ]
    );

    $resolved = $this->advisor->resolveCrossScheduleAiConflicts([$eval1, $eval2]);
    $rec2 = $resolved[1]->aiRecommendation;

    expect($rec2['best_candidate_id'])->toBeNull()
        ->and($rec2['summary_explanation'])->toContain('Seluruh kandidat pengganti bentrok penugasan');
});

test('it correctly identifies admin users strictly via allowlist in isAdmin method', function () {
    $admin1 = User::where('email', 'admin@palcomtech.ac.id')->firstOrFail();
    $admin2 = User::where('email', 'admin@example.com')->firstOrFail();
    $spoofedUser = User::factory()->create(['email' => 'admin_test@gmail.com']);
    $regularUser = User::factory()->create(['email' => 'dosen@palcomtech.ac.id']);
    $outsideUser = User::factory()->create(['email' => 'student@gmail.com']);

    expect($admin1->isAdmin())->toBeTrue()
        ->and($admin2->isAdmin())->toBeTrue()
        ->and($spoofedUser->isAdmin())->toBeFalse()
        ->and($regularUser->isAdmin())->toBeFalse()
        ->and($outsideUser->isAdmin())->toBeFalse();
});

test('it authorizes allowlisted admin user to execute analyzeWithAi successfully', function () {
    $admin = User::factory()->admin()->create();
    expect($admin->isAdmin())->toBeTrue();
    $this->actingAs($admin);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Analisis admin sukses.',
                                    'rankings' => [
                                        ['instructor_id' => 2, 'instructor_name' => 'Kanero', 'rank' => 1, 'confidence_score' => 95, 'ai_reasoning' => 'Admin test.'],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'modelVersion' => 'gemini-3.5-flash-lite',
        ], 200),
    ]);

    $leave = InstructorLeave::firstOrFail();

    Livewire::test(AiScheduler::class)
        ->call('selectLeave', $leave->id)
        ->call('analyzeWithAi')
        ->assertSuccessful()
        ->assertHasNoErrors();

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'generativelanguage.googleapis.com'));
});

test('it rejects regular non-admin user with 403 on analyzeWithAi without calling Gemini API', function () {
    $regularUser = User::factory()->create(['email' => 'regular_staff@palcomtech.ac.id']);
    expect($regularUser->isAdmin())->toBeFalse();

    $this->actingAs($regularUser);

    Http::fake();

    $leave = InstructorLeave::firstOrFail();

    // 1. Ditolak pada AiScheduler
    Livewire::test(AiScheduler::class)
        ->call('selectLeave', $leave->id)
        ->call('analyzeWithAi')
        ->assertForbidden();

    // 2. Ditolak pada InstructorLeaveForm
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', '2026-11-25')
        ->set('form.start_time', '13:00')
        ->set('form.end_time', '15:00')
        ->call('submitLeave')
        ->call('analyzeWithAi')
        ->assertForbidden();

    // Pastikan pengguna yang ditolak tidak menyebabkan request ke Gemini API
    Http::assertNothingSent();
});

test('it rejects user with spoofed email prefix like admin_test@gmail.com with 403 without calling Gemini API', function () {
    $spoofedUser = User::factory()->create(['email' => 'admin_test@gmail.com']);
    expect($spoofedUser->isAdmin())->toBeFalse();

    $this->actingAs($spoofedUser);

    Http::fake();

    $leave = InstructorLeave::firstOrFail();

    // 1. Ditolak pada AiScheduler
    Livewire::test(AiScheduler::class)
        ->call('selectLeave', $leave->id)
        ->call('analyzeWithAi')
        ->assertForbidden();

    // 2. Ditolak pada InstructorLeaveForm
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', '2026-11-25')
        ->set('form.start_time', '13:00')
        ->set('form.end_time', '15:00')
        ->call('submitLeave')
        ->call('analyzeWithAi')
        ->assertForbidden();

    // Pastikan pengguna berpura-pura admin tidak menyebabkan request ke Gemini API
    Http::assertNothingSent();
});

test('it rejects unauthenticated guests with 403 on analyzeWithAi without calling Gemini API', function () {
    Http::fake();

    $leave = InstructorLeave::firstOrFail();

    // 1. Ditolak pada AiScheduler
    Livewire::test(AiScheduler::class)
        ->call('selectLeave', $leave->id)
        ->call('analyzeWithAi')
        ->assertForbidden();

    // 2. Ditolak pada InstructorLeaveForm
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', '2026-11-25')
        ->set('form.start_time', '13:00')
        ->set('form.end_time', '15:00')
        ->call('submitLeave')
        ->call('analyzeWithAi')
        ->assertForbidden();

    // Pastikan request ke Gemini API tidak dipanggil sama sekali
    Http::assertNothingSent();
});

test('it falls back gracefully and marks fallback mode explicitly when Gemini API fails', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response('Service Unavailable', 503),
    ]);

    $deterministicResult = $this->engine->evaluateLeave($leave);
    $enhancedResult = $this->advisor->enhanceEvaluation($deterministicResult);

    expect($enhancedResult->totalResolvedSchedules)->toBe(2)
        ->and($enhancedResult->allSchedulesResolved)->toBeTrue()
        ->and($enhancedResult->aiSummary)->not->toBeNull()
        ->and($enhancedResult->aiSummary['status'])->toBe('fallback')
        ->and($enhancedResult->aiSummary['is_ai_generated'])->toBeFalse()
        ->and($enhancedResult->aiSummary['fallback_used'])->toBeTrue()
        ->and($enhancedResult->aiSummary['executive_summary'])->toContain('Mode Fallback Aktif');

    $excelEval = collect($enhancedResult->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');
    expect($excelEval->aiRecommendation)->not->toBeNull()
        ->and($excelEval->aiRecommendation['status'])->toBe('fallback')
        ->and($excelEval->aiRecommendation['is_ai_generated'])->toBeFalse()
        ->and($excelEval->aiRecommendation['fallback_used'])->toBeTrue()
        ->and($excelEval->aiRecommendation['summary_explanation'])->toContain('Mode Fallback')
        ->and($excelEval->aiRecommendation['rankings'])->not->toBeEmpty();
});

test('it falls back when Gemini API returns malformed or non-JSON output', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Teks bukan JSON sama sekali'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $deterministicResult = $this->engine->evaluateLeave($leave);
    $enhancedResult = $this->advisor->enhanceEvaluation($deterministicResult);

    $excelEval = collect($enhancedResult->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');
    expect($excelEval->aiRecommendation['status'])->toBe('fallback')
        ->and($excelEval->aiRecommendation['fallback_used'])->toBeTrue()
        ->and($enhancedResult->aiSummary['status'])->toBe('fallback')
        ->and($enhancedResult->aiSummary['fallback_used'])->toBeTrue();
});

test('it prevents hallucinated candidates by filtering out candidates not in valid pool', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Uji halusinasi kandidat...',
                                    'rankings' => [
                                        [
                                            'instructor_id' => 99999,
                                            'instructor_name' => 'Instruktur Palsu',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Tidak terdaftar.',
                                            'confidence_score' => 99,
                                        ],
                                        [
                                            'instructor_id' => 2,
                                            'instructor_name' => 'Kanero',
                                            'rank' => 2,
                                            'ai_reasoning' => 'Valid dan kompeten.',
                                            'confidence_score' => 90,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $deterministicResult = $this->engine->evaluateLeave($leave);
    $enhancedResult = $this->advisor->enhanceEvaluation($deterministicResult);

    $excelEval = collect($enhancedResult->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');
    $rankedIds = collect($excelEval->aiRecommendation['rankings'])->pluck('instructor_id')->toArray();

    expect($rankedIds)->not->toContain(99999)
        ->and($rankedIds)->toContain(2)
        ->and($excelEval->aiRecommendation['rankings'][0]['rank'])->toBe(1);
});

test('it triggers analyzeWithAi action in InstructorLeaveForm Livewire component when authenticated', function () {
    $this->actingAs($this->user);

    $date = '2026-11-25';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $excelClass = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $room1 = Room::where('name', 'Lab 1')->firstOrFail();

    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass->id,
        'room_id' => $room1->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Analisis AI via Livewire berhasil.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => 2,
                                            'instructor_name' => 'Kanero',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Sangat cocok.',
                                            'confidence_score' => 95,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'modelVersion' => 'gemini-3.5-flash-lite',
        ], 200),
    ]);

    $test = Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', $date)
        ->set('form.start_time', '13:00')
        ->set('form.end_time', '15:00')
        ->call('submitLeave')
        ->assertHasNoErrors();

    // Awalnya hasil evaluasi bersifat deterministik murni
    $schedResult = $test->get('schedulingResult');
    expect($schedResult['affected_schedules'][0]['ai_recommendation'])->toBeNull();

    // Admin secara eksplisit meminta analisis AI
    $test->call('analyzeWithAi')
        ->assertHasNoErrors();

    $updatedResult = $test->get('schedulingResult');
    expect($updatedResult['affected_schedules'][0]['ai_recommendation'])->not->toBeNull()
        ->and($updatedResult['affected_schedules'][0]['ai_recommendation']['is_ai_generated'])->toBeTrue()
        ->and($updatedResult['ai_summary']['status'])->toBe('success')
        ->and($test->get('feedbackMessage'))->toContain('Google Gemini 3.5 Flash-Lite');

    $test->assertSee('Penjelasan Gemini 3.5 Flash-Lite');
});

test('it clearly reports fallback in InstructorLeaveForm feedback message when Gemini API fails', function () {
    $this->actingAs($this->user);

    $date = '2026-11-25';
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $excelClass = CourseClass::where('subject', 'Microsoft Excel')->firstOrFail();
    $room1 = Room::where('name', 'Lab 1')->firstOrFail();

    Schedule::factory()->create([
        'instructor_id' => $wahyu->id,
        'course_class_id' => $excelClass->id,
        'room_id' => $room1->id,
        'date' => $date,
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'scheduled',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response('Server Error', 500),
    ]);

    $test = Livewire::test(InstructorLeaveForm::class)
        ->set('form.instructor_id', $wahyu->id)
        ->set('form.date', $date)
        ->set('form.start_time', '13:00')
        ->set('form.end_time', '15:00')
        ->call('submitLeave')
        ->call('analyzeWithAi');

    expect($test->get('feedbackMessage'))->toContain('Mode Fallback');
    $test->assertSee('Mode Fallback');
});

test('it supports analyzeWithAi in AiScheduler and resets AI state on leave change', function () {
    $this->actingAs($this->user);

    $leave1 = InstructorLeave::firstOrFail();
    $leave2 = InstructorLeave::factory()->create([
        'instructor_id' => $leave1->instructor_id,
        'date' => '2026-12-05',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => 'pending',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Penjelasan AI pada AiScheduler.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => 2,
                                            'instructor_name' => 'Kanero',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Sangat cocok.',
                                            'confidence_score' => 95,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'modelVersion' => 'gemini-3.5-flash-lite',
        ], 200),
    ]);

    $component = Livewire::test(AiScheduler::class);

    // Initial mount: evaluasi deterministik murni, ai_summary null
    $initialResult = $component->get('result');
    expect($initialResult)->not->toBeNull()
        ->and($initialResult['ai_summary'])->toBeNull();

    // Jalankan analisis AI
    $component->call('analyzeWithAi');
    $aiResult = $component->get('result');
    expect($aiResult['ai_summary'])->not->toBeNull()
        ->and($aiResult['ai_summary']['is_ai_generated'])->toBeTrue()
        ->and($aiResult['ai_summary']['status'])->toBe('success')
        ->and($component->get('feedbackMessage'))->toContain('Google Gemini 3.5 Flash-Lite');

    $component->assertSee('Gemini 3.5 Flash-Lite');

    // Mengganti izin: status AI harus di-reset kembali ke deterministik murni
    $component->call('selectLeave', $leave2->id);
    $resetResult = $component->get('result');
    expect($resetResult['leave_id'])->toBe($leave2->id)
        ->and($resetResult['ai_summary'])->toBeNull()
        ->and($component->get('feedbackMessage'))->toBeNull();
});

test('it handles fallback state in AiScheduler when Gemini API fails', function () {
    $this->actingAs($this->user);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response('Gateway Timeout', 504),
    ]);

    $component = Livewire::test(AiScheduler::class)
        ->call('analyzeWithAi');

    $result = $component->get('result');
    expect($result['ai_summary'])->not->toBeNull()
        ->and($result['ai_summary']['status'])->toBe('fallback')
        ->and($result['ai_summary']['fallback_used'])->toBeTrue()
        ->and($component->get('feedbackMessage'))->toContain('Mode Fallback');

    $component->assertSee('Mode Fallback Aktif');
});

test('it runs schedule:evaluate command with --ai option using mock API', function () {
    $wahyuLeave = InstructorLeave::firstOrFail();

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => 2,
                                    'summary_explanation' => 'Penjelasan AI untuk CLI.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => 2,
                                            'instructor_name' => 'Kanero',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Instruktur ideal.',
                                            'confidence_score' => 92,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('schedule:evaluate', ['leave_id' => $wahyuLeave->id, '--ai' => true])
        ->expectsOutputToContain('GOOGLE GEMINI 3.5 FLASH-LITE')
        ->expectsOutputToContain('gemini-3.5-flash-lite')
        ->expectsOutputToContain('Penjelasan AI: Penjelasan AI untuk CLI.')
        ->assertSuccessful();
});
