<?php

namespace App\Services\Scheduling;

use App\Services\Scheduling\DTOs\ScheduleEvaluation;
use App\Services\Scheduling\DTOs\SchedulingResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiSchedulingAdvisor
{
    protected string $apiKey;

    protected string $model;

    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
        $this->baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    /**
     * Check if Gemini API key is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Get the active model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Enhance an existing deterministic SchedulingResult with structured AI ranking and explanations.
     * Scheduling Engine remains the sole authority for eligibility; AI only analyzes valid candidates.
     */
    public function enhanceEvaluation(SchedulingResult $result): SchedulingResult
    {
        $enhancedSchedules = [];

        foreach ($result->affectedSchedules as $scheduleEval) {
            $enhancedSchedules[] = $this->enhanceScheduleEvaluation($scheduleEval);
        }

        // Generate executive summary from AI if there are affected schedules
        $aiSummary = $this->generateExecutiveSummary($result, $enhancedSchedules);

        return new SchedulingResult(
            leaveId: $result->leaveId,
            instructorId: $result->instructorId,
            instructorName: $result->instructorName,
            leaveDate: $result->leaveDate,
            leaveStartTime: $result->leaveStartTime,
            leaveEndTime: $result->leaveEndTime,
            leaveReason: $result->leaveReason,
            affectedSchedules: $enhancedSchedules,
            totalAffectedSchedules: $result->totalAffectedSchedules,
            totalResolvedSchedules: $result->totalResolvedSchedules,
            allSchedulesResolved: $result->allSchedulesResolved,
            summary: $result->summary,
            aiSummary: $aiSummary,
        );
    }

    /**
     * Enhance a single schedule evaluation with AI reasoning for valid candidates.
     */
    public function enhanceScheduleEvaluation(ScheduleEvaluation $eval): ScheduleEvaluation
    {
        // Rule: Only rank and explain when there are valid candidates
        if (empty($eval->validCandidates)) {
            $aiData = [
                'is_ai_generated' => false,
                'model' => $this->model,
                'summary_explanation' => 'Tidak ada kandidat pengganti yang memenuhi syarat untuk dianalisis oleh AI.',
                'rankings' => [],
                'fallback_used' => false,
            ];

            return $this->rebuildScheduleEvaluation($eval, $aiData);
        }

        $aiData = $this->callGeminiForSchedule($eval);

        return $this->rebuildScheduleEvaluation($eval, $aiData);
    }

    /**
     * Call Gemini API to rank and explain valid candidates using structured JSON.
     *
     * @return array<string, mixed>
     */
    protected function callGeminiForSchedule(ScheduleEvaluation $eval): array
    {
        if (! $this->isConfigured()) {
            return $this->buildFallbackData($eval, 'GEMINI_API_KEY belum dikonfigurasi.');
        }

        $prompt = $this->buildPromptForSchedule($eval);

        try {
            $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.2,
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Gemini API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->buildFallbackData($eval, "HTTP {$response->status()}: Gagal menghubungi Gemini API.");
            }

            $json = $response->json();
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! $rawText) {
                return $this->buildFallbackData($eval, 'Respons teks kosong dari Gemini API.');
            }

            $parsed = json_decode($rawText, true);
            if (! is_array($parsed) || empty($parsed['rankings'])) {
                return $this->buildFallbackData($eval, 'Format JSON respons AI tidak sesuai skema.');
            }

            // Validasi ketat: Hanya izinkan instruktur yang memang ada dalam validCandidates
            $validCandidateIds = collect($eval->validCandidates)->pluck('instructorId')->all();
            $sanitizedRankings = [];

            foreach ($parsed['rankings'] as $item) {
                $candId = (int) ($item['instructor_id'] ?? 0);
                if (in_array($candId, $validCandidateIds, true)) {
                    $sanitizedRankings[] = [
                        'instructor_id' => $candId,
                        'instructor_name' => (string) ($item['instructor_name'] ?? ''),
                        'rank' => (int) ($item['rank'] ?? (count($sanitizedRankings) + 1)),
                        'ai_reasoning' => (string) ($item['ai_reasoning'] ?? ''),
                        'confidence_score' => (int) ($item['confidence_score'] ?? 80),
                    ];
                }
            }

            if (empty($sanitizedRankings)) {
                return $this->buildFallbackData($eval, 'Kandidat dalam respons AI tidak cocok dengan kandidat valid.');
            }

            // Urutkan berdasarkan rank terkecil (rank 1 pertama)
            usort($sanitizedRankings, fn ($a, $b) => $a['rank'] <=> $b['rank']);

            return [
                'is_ai_generated' => true,
                'model' => $this->model,
                'best_candidate_id' => $sanitizedRankings[0]['instructor_id'],
                'summary_explanation' => (string) ($parsed['summary_explanation'] ?? 'Rekomendasi dianalisis oleh Gemini AI.'),
                'rankings' => $sanitizedRankings,
                'fallback_used' => false,
            ];
        } catch (Throwable $e) {
            Log::error('Exception during Gemini API evaluation', ['exception' => $e->getMessage()]);

            return $this->buildFallbackData($eval, 'Terjadi kendala koneksi ke Gemini API: '.$e->getMessage());
        }
    }

    /**
     * Build prompt with rich context and strict schema instruction.
     */
    protected function buildPromptForSchedule(ScheduleEvaluation $eval): string
    {
        $validListText = '';
        foreach ($eval->validCandidates as $idx => $c) {
            $num = $idx + 1;
            $reasons = implode('; ', $c->reasons);
            $validListText .= "{$num}. ID: {$c->instructorId}, Nama: {$c->instructorName}, Tingkat Kompetensi: {$c->skillLevel}, Beban Mengajar Hari Ini: {$c->otherClassesCountToday} kelas, Skor Sistem: {$c->score}, Alasan: {$reasons}\n";
        }

        $roomText = $eval->roomEvaluation
            ? "{$eval->roomEvaluation->roomName} (Kapasitas: {$eval->roomEvaluation->capacity}, Catatan: {$eval->roomEvaluation->notes})"
            : 'Belum ditentukan';

        return <<<PROMPT
Anda adalah asisten AI Penjadwalan Akademik PALCOM AI Scheduler.
Tugas Anda adalah menganalisis dan memeringkat kandidat instruktur pengganti yang SUDAH LOLOS validasi kelayakan deterministik, serta memberikan penjelasan profesional dalam Bahasa Indonesia yang natural.

INFORMASI KELAS TERDAMPAK:
- Kelas: {$eval->className}
- Mata Pelajaran: {$eval->subject}
- Tanggal: {$eval->date}
- Waktu: {$eval->startTime} - {$eval->endTime}
- Ruangan: {$roomText}

DAFTAR KANDIDAT PENGGANTI YANG VALID (Lolos Seleksi):
{$validListText}

ATURAN WAJIB:
1. HANYA peringkat kandidat yang tercantum dalam DAFTAR KANDIDAT PENGGANTI YANG VALID di atas. DILARANG KERAS merekomendasikan atau menyebutkan nama instruktur di luar daftar ini.
2. Berikan ranking peringkat (rank 1, 2, dst.), skor keyakinan AI (1-100), dan alasan spesifik mengapa instruktur peringkat 1 paling direkomendasikan.
3. Berikan output HANYA dalam format JSON terstruktur dengan format berikut:
{
  "best_candidate_id": 2,
  "summary_explanation": "Penjelasan mengapa kandidat utama dipilih dan perbandingannya...",
  "rankings": [
    {
      "instructor_id": 2,
      "instructor_name": "Nama Instruktur",
      "rank": 1,
      "ai_reasoning": "Alasan mendalam...",
      "confidence_score": 95
    }
  ]
}
PROMPT;
    }

    /**
     * Build deterministic fallback data when AI call fails or is unavailable.
     *
     * @return array<string, mixed>
     */
    protected function buildFallbackData(ScheduleEvaluation $eval, string $reason): array
    {
        $fallbackRankings = [];
        foreach ($eval->validCandidates as $idx => $c) {
            $fallbackRankings[] = [
                'instructor_id' => $c->instructorId,
                'instructor_name' => $c->instructorName,
                'rank' => $idx + 1,
                'ai_reasoning' => implode('; ', $c->reasons),
                'confidence_score' => $c->score,
            ];
        }

        return [
            'is_ai_generated' => false,
            'model' => $this->model,
            'best_candidate_id' => $eval->bestCandidate?->instructorId,
            'summary_explanation' => "Menggunakan rekomendasi sistem deterministik ({$reason}).",
            'rankings' => $fallbackRankings,
            'fallback_used' => true,
            'fallback_reason' => $reason,
        ];
    }

    /**
     * Generate overall executive summary across all affected schedules.
     *
     * @param  list<ScheduleEvaluation>  $enhancedSchedules
     * @return array<string, mixed>
     */
    protected function generateExecutiveSummary(SchedulingResult $result, array $enhancedSchedules): array
    {
        $total = count($enhancedSchedules);
        $resolved = count(array_filter($enhancedSchedules, fn ($s) => $s->isResolved()));
        $hasAi = collect($enhancedSchedules)->contains(fn ($s) => ($s->aiRecommendation['is_ai_generated'] ?? false) === true);

        if ($total === 0) {
            $text = 'Tidak ada jadwal mengajar yang terdampak pada periode izin ini. Tidak diperlukan tindakan penggantian instruktur.';
        } elseif ($resolved === $total) {
            $text = "Seluruh {$total} kelas terdampak berhasil dicarikan rekomendasi instruktur pengganti yang kompeten dan bebas bentrok jadwal/ruangan. Rekomendasi siap ditinjau dan disetujui oleh admin.";
        } else {
            $text = "Ditemukan {$total} kelas terdampak, {$resolved} kelas berhasil dicarikan solusi, dan ".($total - $resolved).' kelas memerlukan penyesuaian khusus (ketiadaan kandidat atau kendala ruangan).';
        }

        return [
            'is_ai_generated' => $hasAi,
            'model' => $this->model,
            'executive_summary' => $text,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Helper to clone ScheduleEvaluation with updated aiRecommendation.
     *
     * @param  array<string, mixed>  $aiData
     */
    protected function rebuildScheduleEvaluation(ScheduleEvaluation $eval, array $aiData): ScheduleEvaluation
    {
        return new ScheduleEvaluation(
            scheduleId: $eval->scheduleId,
            courseClassId: $eval->courseClassId,
            className: $eval->className,
            subject: $eval->subject,
            date: $eval->date,
            startTime: $eval->startTime,
            endTime: $eval->endTime,
            roomEvaluation: $eval->roomEvaluation,
            status: $eval->status,
            candidates: $eval->candidates,
            validCandidates: $eval->validCandidates,
            disqualifiedCandidates: $eval->disqualifiedCandidates,
            bestCandidate: $eval->bestCandidate,
            summary: $eval->summary,
            warnings: $eval->warnings,
            aiRecommendation: $aiData,
        );
    }
}
