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

        // Pastikan hasil ranking Gemini tidak menyebabkan rekomendasi instruktur yang sama untuk kelas beririsan
        $enhancedSchedules = $this->resolveCrossScheduleAiConflicts($enhancedSchedules);

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
                'status' => 'not_applicable',
                'is_ai_generated' => false,
                'model' => $this->model,
                'best_candidate_id' => null,
                'summary_explanation' => 'Tidak ada kandidat pengganti yang memenuhi syarat untuk dianalisis oleh AI.',
                'rankings' => [],
                'fallback_used' => false,
                'fallback_reason' => null,
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
            // Keamanan: Kirim API key melalui header x-goog-api-key, bukan di URL query parameter
            $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent";

            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ])
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
                // Keamanan: Hanya catat status HTTP, jangan catat body atau kredensial sensitif
                Log::warning('Gemini API call failed', [
                    'status' => $response->status(),
                ]);

                return $this->buildFallbackData($eval, "Layanan Gemini AI tidak dapat diakses (HTTP {$response->status()}).");
            }

            $json = $response->json();
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! $rawText) {
                return $this->buildFallbackData($eval, 'Respons teks kosong dari Gemini API.');
            }

            $parsed = json_decode($rawText, true);
            $rawRankings = $parsed['rankings'] ?? null;
            if (! is_array($parsed) || ! is_array($rawRankings) || empty($rawRankings)) {
                return $this->buildFallbackData($eval, 'Format JSON respons AI tidak sesuai skema.');
            }

            // Validasi ketat:
            // 1. ID kandidat harus ada di dalam validCandidates deterministik
            // 2. ID kandidat harus unik (tidak boleh duplikat)
            // 3. Nama HARUS diambil dari database/deterministik, BUKAN dari teks buatan AI
            // 4. Skor keyakinan harus dalam rentang [0, 100]
            // 5. Ranking tidak boleh duplikat (dinormalisasi secara sekuensial)
            $validCandidatesById = collect($eval->validCandidates)->keyBy('instructorId');
            $sanitizedRankings = [];
            $seenCandidateIds = [];

            foreach ($rawRankings as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $candId = (int) ($item['instructor_id'] ?? 0);

                // 1. Validasi ID kandidat
                if (! $validCandidatesById->has($candId)) {
                    continue;
                }

                // 2. Cegah duplikasi ID kandidat
                if (isset($seenCandidateIds[$candId])) {
                    continue;
                }
                $seenCandidateIds[$candId] = true;

                // 3. Ambil nama resmi dari database / hasil deterministik
                $matchedCandidate = $validCandidatesById->get($candId);
                $officialName = $matchedCandidate->instructorName;

                // 4. Validasi rentang skor keyakinan [0, 100]
                $rawScore = $item['confidence_score'] ?? 80;
                $confidenceScore = is_numeric($rawScore) ? max(0, min(100, (int) $rawScore)) : 80;

                // Rank awal dari respon AI
                $rawRank = isset($item['rank']) && is_numeric($item['rank']) ? (int) $item['rank'] : PHP_INT_MAX;

                $reasoning = trim((string) ($item['ai_reasoning'] ?? ''));
                if ($reasoning === '') {
                    $reasoning = 'Direkomendasikan berdasarkan evaluasi kompetensi dan ketersediaan waktu.';
                }

                $sanitizedRankings[] = [
                    'instructor_id' => $candId,
                    'instructor_name' => $officialName,
                    'raw_rank' => $rawRank,
                    'ai_reasoning' => $reasoning,
                    'confidence_score' => $confidenceScore,
                ];
            }

            if (empty($sanitizedRankings)) {
                return $this->buildFallbackData($eval, 'Kandidat dalam respons AI tidak cocok dengan kandidat valid.');
            }

            // 5. Ranking tidak boleh duplikat: urutkan berdasarkan raw_rank ascending, jika sama urutkan berdasarkan confidence_score descending
            usort($sanitizedRankings, function ($a, $b) {
                if ($a['raw_rank'] === $b['raw_rank']) {
                    return $b['confidence_score'] <=> $a['confidence_score'];
                }

                return $a['raw_rank'] <=> $b['raw_rank'];
            });

            // Normalisasikan ranking menjadi urutan sekuensial unik 1, 2, ...
            $finalRankings = [];
            foreach ($sanitizedRankings as $index => $ranked) {
                $finalRankings[] = [
                    'instructor_id' => $ranked['instructor_id'],
                    'instructor_name' => $ranked['instructor_name'],
                    'rank' => $index + 1,
                    'ai_reasoning' => $ranked['ai_reasoning'],
                    'confidence_score' => $ranked['confidence_score'],
                ];
            }

            return [
                'status' => 'success',
                'is_ai_generated' => true,
                'model' => $this->model,
                'best_candidate_id' => $finalRankings[0]['instructor_id'],
                'summary_explanation' => (string) ($parsed['summary_explanation'] ?? 'Rekomendasi dianalisis oleh Gemini AI.'),
                'rankings' => $finalRankings,
                'fallback_used' => false,
                'fallback_reason' => null,
            ];
        } catch (Throwable $e) {
            // Keamanan: Jangan catat raw message atau detail sensitif ke log maupun UI
            Log::error('Exception during Gemini API evaluation', [
                'exception_class' => get_class($e),
            ]);

            return $this->buildFallbackData($eval, 'Kendala koneksi jaringan ke layanan AI.');
        }
    }

    /**
     * Resolve cross-schedule instructor assignment conflicts across overlapping classes.
     * Ensures the same instructor is not recommended for two classes with overlapping time windows.
     *
     * @param  list<ScheduleEvaluation>  $enhancedSchedules
     * @return list<ScheduleEvaluation>
     */
    public function resolveCrossScheduleAiConflicts(array $enhancedSchedules): array
    {
        /** @var list<array{instructor_id: int, start_time: string, end_time: string, class_name: string}> $allocatedInstructors */
        $allocatedInstructors = [];

        foreach ($enhancedSchedules as $index => $eval) {
            if (empty($eval->aiRecommendation) || empty($eval->aiRecommendation['rankings'])) {
                continue;
            }

            $rankings = $eval->aiRecommendation['rankings'];
            $selectedCandidateId = null;
            $selectedRankItem = null;
            $conflictingClass = null;

            // Cari kandidat dengan peringkat tertinggi yang tidak bentrok waktu dengan kelas yang sudah dialokasikan
            foreach ($rankings as $rankItem) {
                $candId = (int) $rankItem['instructor_id'];
                $hasOverlap = false;

                foreach ($allocatedInstructors as $alloc) {
                    if ($alloc['instructor_id'] === $candId && $this->intervalsOverlap($alloc['start_time'], $alloc['end_time'], $eval->startTime, $eval->endTime)) {
                        $hasOverlap = true;
                        $conflictingClass = $alloc['class_name'];
                        break;
                    }
                }

                if (! $hasOverlap) {
                    $selectedCandidateId = $candId;
                    $selectedRankItem = $rankItem;
                    break;
                }
            }

            $aiData = $eval->aiRecommendation;

            if ($selectedCandidateId !== null) {
                $allocatedInstructors[] = [
                    'instructor_id' => $selectedCandidateId,
                    'start_time' => $eval->startTime,
                    'end_time' => $eval->endTime,
                    'class_name' => $eval->className,
                ];

                // Jika kandidat peringkat teratas bentrok dan dialihkan ke peringkat berikutnya
                if ($selectedCandidateId !== ($aiData['best_candidate_id'] ?? null)) {
                    $aiData['best_candidate_id'] = $selectedCandidateId;
                    $aiData['summary_explanation'] .= " (Penyesuaian Konflik: Dialihkan ke {$selectedRankItem['instructor_name']} karena kandidat peringkat sebelumnya telah ditugaskan pada kelas '{$conflictingClass}' yang jadwalnya bersamaan).";
                    $enhancedSchedules[$index] = $this->rebuildScheduleEvaluation($eval, $aiData);
                }
            } else {
                // Seluruh kandidat bentrok dengan kelas lain yang bersamaan
                $aiData['best_candidate_id'] = null;
                $aiData['summary_explanation'] .= ' (Perhatian: Seluruh kandidat pengganti bentrok penugasan dengan kelas terdampak lain yang jadwalnya bersamaan).';
                $enhancedSchedules[$index] = $this->rebuildScheduleEvaluation($eval, $aiData);
            }
        }

        return $enhancedSchedules;
    }

    /**
     * Helper to check if two time intervals overlap.
     */
    protected function intervalsOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return max($startA, $startB) < min($endA, $endB);
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
                'confidence_score' => null, // Confidence score adalah metrik khusus AI, tidak diisi dengan skor deterministik
            ];
        }

        return [
            'status' => 'fallback',
            'is_ai_generated' => false,
            'model' => $this->model,
            'best_candidate_id' => $eval->bestCandidate?->instructorId,
            'summary_explanation' => "Mode Fallback: Menggunakan rekomendasi deterministik sistem (Alasan: {$reason}).",
            'rankings' => $fallbackRankings,
            'fallback_used' => true,
            'fallback_reason' => $reason,
        ];
    }

    /**
     * Generate overall executive summary across all affected schedules.
     * Distinguishes 4 statuses: 'success', 'partial', 'fallback', 'not_applicable'.
     *
     * @param  list<ScheduleEvaluation>  $enhancedSchedules
     * @return array<string, mixed>
     */
    protected function generateExecutiveSummary(SchedulingResult $result, array $enhancedSchedules): array
    {
        $total = count($enhancedSchedules);
        $eligibleSchedules = collect($enhancedSchedules)->filter(fn ($s) => ! empty($s->validCandidates))->values();
        $eligibleCount = $eligibleSchedules->count();

        $successCount = $eligibleSchedules->filter(fn ($s) => ($s->aiRecommendation['status'] ?? '') === 'success')->count();
        $fallbackCount = $eligibleSchedules->filter(fn ($s) => ($s->aiRecommendation['status'] ?? '') === 'fallback')->count();

        // 1. Not Applicable: tidak ada kelas terdampak atau tidak ada kandidat valid sama sekali
        if ($total === 0 || $eligibleCount === 0) {
            $reason = $total === 0
                ? 'Tidak ada jadwal mengajar yang terdampak pada periode izin ini. Analisis AI tidak diperlukan.'
                : 'Tidak ada kandidat pengganti yang memenuhi syarat untuk dianalisis oleh Gemini AI. Rekomendasi penjadwalan memerlukan tindakan manual admin.';

            return [
                'status' => 'not_applicable',
                'is_ai_generated' => false,
                'model' => $this->model,
                'executive_summary' => $reason,
                'fallback_used' => false,
                'fallback_reason' => null,
                'generated_at' => now()->toIso8601String(),
            ];
        }

        // 2. Success: seluruh kelas yang memenuhi syarat berhasil dianalisis penuh oleh Gemini AI
        if ($eligibleCount > 0 && $successCount === $eligibleCount) {
            $text = "Analisis Gemini AI ({$this->model}): Seluruh {$eligibleCount} kelas terdampak berhasil dianalisis dan diperingkat berdasarkan kesesuaian keahlian serta beban mengajar. Rekomendasi siap ditinjau dan disetujui oleh admin.";

            return [
                'status' => 'success',
                'is_ai_generated' => true,
                'model' => $this->model,
                'executive_summary' => $text,
                'fallback_used' => false,
                'fallback_reason' => null,
                'generated_at' => now()->toIso8601String(),
            ];
        }

        // 3. Partial: sebagian kelas berhasil dianalisis, sebagian menggunakan fallback
        if ($eligibleCount > 0 && $successCount > 0 && $fallbackCount > 0) {
            $text = "Analisis Sebagian (Partial): {$successCount} kelas berhasil dianalisis dengan Gemini AI ({$this->model}), sementara {$fallbackCount} kelas menggunakan rekomendasi deterministik sistem akibat kendala API.";

            return [
                'status' => 'partial',
                'is_ai_generated' => true,
                'model' => $this->model,
                'executive_summary' => $text,
                'fallback_used' => true,
                'fallback_reason' => "Sebagian kelas ({$fallbackCount}) menggunakan fallback deterministik.",
                'generated_at' => now()->toIso8601String(),
            ];
        }

        // 4. Fallback: seluruh kelas eligible gagal dan beralih ke deterministik
        $reasons = collect($enhancedSchedules)
            ->pluck('aiRecommendation.fallback_reason')
            ->filter()
            ->unique()
            ->implode('; ');
        $reasonText = $reasons ?: 'Layanan AI tidak dapat diakses';

        return [
            'status' => 'fallback',
            'is_ai_generated' => false,
            'model' => $this->model,
            'executive_summary' => "Mode Fallback Aktif: Analisis AI Gemini tidak tersedia ({$reasonText}). Rekomendasi dihitung menggunakan Scheduling Engine deterministik berbasis kompetensi dan ketersediaan.",
            'fallback_used' => true,
            'fallback_reason' => $reasonText,
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
