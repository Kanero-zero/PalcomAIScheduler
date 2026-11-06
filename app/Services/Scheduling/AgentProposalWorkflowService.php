<?php

namespace App\Services\Scheduling;

use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Persists only server-cached and revalidated recommendations produced by the AI agent. */
class AgentProposalWorkflowService
{
    public function __construct(
        private SharedDraftNormalizationService $normalizer,
        private ProposalApprovalConflictValidator $conflictValidator,
    ) {}

    public static function cacheKey(string $token): string
    {
        return 'schedora:agent-recommendations:'.$token;
    }

    public function submit(string $optionLabel, string $previewToken): ScheduleProposal
    {
        $actor = $this->authenticatedAo();
        if (! in_array($optionLabel, ['A', 'B', 'C'], true)) {
            throw ValidationException::withMessages(['option_label' => 'Opsi jadwal harus A, B, atau C.']);
        }
        if (mb_strlen($previewToken) !== 64) {
            throw ValidationException::withMessages(['preview_token' => 'Token rekomendasi agent tidak valid.']);
        }

        $cached = Cache::get(self::cacheKey($previewToken));
        if (! is_array($cached) || (int) ($cached['actor_id'] ?? 0) !== (int) $actor->id
            || ! is_array($cached['options'] ?? null)) {
            throw ValidationException::withMessages([
                'preview_token' => 'Rekomendasi agent tidak tersedia, kedaluwarsa, atau bukan milik pengguna ini.',
            ]);
        }

        $options = collect($cached['options'])->filter(fn (mixed $option): bool => is_array($option))->values();
        $selected = $options->firstWhere('option_label', $optionLabel);
        if (! $selected || ! is_array($selected['proposal_draft'] ?? null)) {
            throw ValidationException::withMessages(['option_label' => 'Opsi agent tidak ditemukan pada rekomendasi tersimpan.']);
        }
        $draft = $this->normalizer->normalize($selected['proposal_draft']);

        return DB::transaction(function () use ($actor, $draft, $options, $selected): ScheduleProposal {
            $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($lockedActor->role !== 'ao') {
                throw new AuthorizationException('Hanya AO yang dapat mengajukan jadwal.');
            }

            $proposalForValidation = new ScheduleProposal($draft);
            $optionForValidation = new ScheduleProposalOption([
                'proposal_id' => null,
                'proposed_schedules_payload' => $selected['proposed_schedules_payload'],
            ]);
            $this->conflictValidator->validateOption($proposalForValidation, $optionForValidation, failClosed: true);

            $existing = ScheduleProposal::query()
                ->where('requested_by', $actor->id)
                ->where('status', 'pending')
                ->where('input_mode', 'ai_assistant')
                ->where('title', $draft['title'])
                ->where('start_date', $draft['start_date'])
                ->where('end_date', $draft['end_date'])
                ->with('selectedOption')
                ->get()
                ->first(fn (ScheduleProposal $proposal): bool => $proposal->selectedOption?->option_label === $selected['option_label']
                    && $proposal->selectedOption?->proposed_schedules_payload == $selected['proposed_schedules_payload']);
            if ($existing) {
                return $existing;
            }

            $proposal = ScheduleProposal::create([
                'requested_by' => $actor->id,
                'title' => $draft['title'],
                'input_mode' => 'ai_assistant',
                'department' => $draft['department'],
                'class_kind' => $draft['class_kind'],
                'program_id' => $draft['program_id'],
                'curriculum_version_id' => $draft['curriculum_version_id'],
                'student_count' => $draft['student_count'],
                'start_date' => $draft['start_date'],
                'end_date' => $draft['end_date'],
                'total_meetings' => $draft['total_meetings'],
                'session_duration_minutes' => $draft['session_duration_minutes'],
                'frequency_per_week' => $draft['frequency_per_week'],
                'preferred_weekdays' => $draft['preferred_weekdays'],
                'preferred_time_window' => $draft['preferred_time_window'],
                'preferred_instructor_id' => null,
                'preferred_room_id' => null,
                'required_competency_ids' => $draft['required_competency_ids'],
                'required_facility_codes' => $draft['required_facility_codes'],
                'status' => 'pending',
                'submitted_at' => Carbon::now('Asia/Jakarta'),
            ]);

            $selectedRecord = null;
            foreach ($options as $option) {
                $record = $proposal->options()->create([
                    'option_label' => $option['option_label'],
                    'score' => $option['score'],
                    'conflicts_count' => 0,
                    'rationale' => $option['explanation'],
                    'proposed_schedules_payload' => $option['proposed_schedules_payload'],
                ]);
                if ($option['option_label'] === $selected['option_label']) {
                    $selectedRecord = $record;
                }
            }
            $proposal->selectOption($selectedRecord);

            return $proposal->fresh(['options', 'selectedOption']);
        }, 3);
    }

    private function authenticatedAo(): User
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException('Silakan login sebelum mengajukan jadwal.');
        }
        if ($actor->role !== 'ao') {
            throw new AuthorizationException('Hanya AO yang dapat menggunakan workflow pengajuan.');
        }

        return $actor;
    }
}
