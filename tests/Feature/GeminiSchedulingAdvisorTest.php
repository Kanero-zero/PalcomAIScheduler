<?php

use App\Livewire\AiScheduler;
use App\Livewire\InstructorLeaveForm;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\Scheduling\GeminiSchedulingAdvisor;
use App\Services\Scheduling\SchedulingEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->engine = app(SchedulingEngine::class);
    $this->advisor = app(GeminiSchedulingAdvisor::class);
});

test('it successfully enhances scheduling evaluation with Gemini 3.5 Flash-Lite via mock API', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    // Mock Gemini API response
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
        ->and($enhancedResult->aiSummary['is_ai_generated'])->toBeTrue()
        ->and($enhancedResult->aiSummary['model'])->toBe('gemini-3.5-flash-lite')
        ->and($enhancedResult->aiSummary['fallback_used'])->toBeFalse()
        ->and($enhancedResult->aiSummary['executive_summary'])->toContain('berhasil dicarikan rekomendasi');

    $excelEval = collect($enhancedResult->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');
    expect($excelEval)->not->toBeNull()
        ->and($excelEval->aiRecommendation)->not->toBeNull()
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
                                            'instructor_name' => 'Hacker Coach', // Nama palsu
                                            'rank' => 1,
                                            'ai_reasoning' => 'Kandidat pertama.',
                                            'confidence_score' => 150, // Skor berlebih
                                        ],
                                        [
                                            'instructor_id' => 2, // ID duplikat
                                            'instructor_name' => 'Hacker Clone',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Kandidat duplikat.',
                                            'confidence_score' => 90,
                                        ],
                                        [
                                            'instructor_id' => 5, // Budi Santoso
                                            'instructor_name' => 'Budi Bukan Resmi',
                                            'rank' => 1, // Rank duplikat dengan kandidat sebelumnya
                                            'ai_reasoning' => 'Kandidat kedua.',
                                            'confidence_score' => -20, // Skor di bawah 0
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

test('it falls back gracefully and marks fallback mode explicitly when Gemini API fails', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    // Mock HTTP error 503 Service Unavailable
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response('Service Unavailable', 503),
    ]);

    $deterministicResult = $this->engine->evaluateLeave($leave);
    $enhancedResult = $this->advisor->enhanceEvaluation($deterministicResult);

    // Pastikan hasil evaluasi deterministik tetap aman
    expect($enhancedResult->totalResolvedSchedules)->toBe(2)
        ->and($enhancedResult->allSchedulesResolved)->toBeTrue();

    // Pastikan aiSummary secara eksplisit menyatakan fallback digunakan
    expect($enhancedResult->aiSummary)->not->toBeNull()
        ->and($enhancedResult->aiSummary['is_ai_generated'])->toBeFalse()
        ->and($enhancedResult->aiSummary['fallback_used'])->toBeTrue()
        ->and($enhancedResult->aiSummary['executive_summary'])->toContain('Mode Fallback Aktif');

    // Pastikan aiRecommendation pada jadwal juga menyatakan fallback digunakan
    $excelEval = collect($enhancedResult->affectedSchedules)->first(fn ($s) => $s->subject === 'Microsoft Excel');
    expect($excelEval->aiRecommendation)->not->toBeNull()
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
    expect($excelEval->aiRecommendation['fallback_used'])->toBeTrue()
        ->and($enhancedResult->aiSummary['fallback_used'])->toBeTrue();
});

test('it prevents hallucinated candidates by filtering out candidates not in valid pool', function () {
    $wahyu = Instructor::where('name', 'Wahyu')->firstOrFail();
    $leave = InstructorLeave::where('instructor_id', $wahyu->id)->firstOrFail();

    // Gemini mengembalikan ID 99999 yang bukan merupakan kandidat valid
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
                                            'instructor_id' => 99999, // Halusinasi
                                            'instructor_name' => 'Instruktur Palsu',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Tidak terdaftar.',
                                            'confidence_score' => 99,
                                        ],
                                        [
                                            'instructor_id' => 2, // Kanero (Valid)
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

    // Pastikan ID 99999 difilter keluar dan hanya ID valid (Kanero) yang tersisa
    expect($rankedIds)->not->toContain(99999)
        ->and($rankedIds)->toContain(2)
        ->and($excelEval->aiRecommendation['rankings'][0]['rank'])->toBe(1);
});

test('it handles schedules with no valid candidates without calling API needlessly', function () {
    $date = '2026-11-30';
    $mainInstructor = Instructor::factory()->create(['status' => 'active']);
    $rareClass = CourseClass::factory()->create(['subject' => 'Quantum Artificial Intelligence']);
    $room = Room::factory()->create(['capacity' => 20, 'status' => 'available']);

    Schedule::factory()->create([
        'instructor_id' => $mainInstructor->id,
        'course_class_id' => $rareClass->id,
        'room_id' => $room->id,
        'date' => $date,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'status' => 'scheduled',
    ]);

    $leave = InstructorLeave::factory()->create([
        'instructor_id' => $mainInstructor->id,
        'date' => $date,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'status' => 'pending',
    ]);

    // Request HTTP tidak boleh dipanggil karena tidak ada kandidat valid
    Http::fake();

    $result = $this->engine->evaluateWithAi($leave);

    Http::assertNothingSent();

    $eval = $result->affectedSchedules[0];
    expect($eval->aiRecommendation)->not->toBeNull()
        ->and($eval->aiRecommendation['is_ai_generated'])->toBeFalse()
        ->and($eval->aiRecommendation['rankings'])->toBeEmpty()
        ->and($eval->aiRecommendation['summary_explanation'])->toContain('Tidak ada kandidat pengganti');
});

test('it triggers analyzeWithAi action in InstructorLeaveForm Livewire component', function () {
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

    // Sekarang admin secara eksplisit meminta analisis AI
    $test->call('analyzeWithAi')
        ->assertHasNoErrors();

    $updatedResult = $test->get('schedulingResult');
    expect($updatedResult['affected_schedules'][0]['ai_recommendation'])->not->toBeNull()
        ->and($updatedResult['affected_schedules'][0]['ai_recommendation']['is_ai_generated'])->toBeTrue()
        ->and($test->get('feedbackMessage'))->toContain('Google Gemini 3.5 Flash-Lite');

    $test->assertSee('Penjelasan Gemini 3.5 Flash-Lite');
});

test('it clearly reports fallback in InstructorLeaveForm feedback message when Gemini API fails', function () {
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
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response('Gateway Timeout', 504),
    ]);

    $component = Livewire::test(AiScheduler::class)
        ->call('analyzeWithAi');

    $result = $component->get('result');
    expect($result['ai_summary'])->not->toBeNull()
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
