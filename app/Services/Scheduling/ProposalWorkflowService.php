<?php

namespace App\Services\Scheduling;

use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * G3-04: AO-only proposal submission and readback. Pending is NOT a reservation.
 * There are no routes or approval decisions in this service.
 */
class ProposalWorkflowService
{
    private const LABELS = ['A', 'B', 'C'];

    public function __construct(
        private SharedDraftNormalizationService $normalizer,
        private DeterministicSchedulingEngine $engine,
    ) {}

    /**
     * Returns server-generated alternatives with opaque, draft-bound preview tokens.
     * Tokens detect stale/tampered previews; they are NOT authorization credentials.
     *
     * @return array{alternatives_count:int,options:array,reasons:array,search_exhausted:bool}
     */
    public function preview(array $input): array
    {
        $draft = $this->normalizer->normalize($input);
        $result = $this->engine->generate($draft);
        foreach ($result['options'] as &$option) {
            $option['preview_token'] = $this->token($draft, $option);
        }
        unset($option);

        return $result;
    }

    /**
     * Persist the currently valid, regenerated options; never trust an option payload
     * supplied by the browser. AO identity is read exclusively from auth()->user().
     *
     * Duplicate identical PENDING submissions by the same AO return the same record.
     * G4 must independently lock/revalidate before writing official schedules.
     */
    public function submit(array $input, string $optionLabel, string $previewToken): ScheduleProposal
    {
        $actor = $this->authenticatedAo();
        if (! in_array($optionLabel, self::LABELS, true)) {
            throw ValidationException::withMessages(['option_label' => 'Opsi jadwal harus A, B, atau C.']);
        }
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $previewToken)) {
            throw ValidationException::withMessages(['preview_token' => 'Token pratinjau tidak valid. Buat ulang rekomendasi.']);
        }

        $draft = $this->normalizer->normalize($input);

        return DB::transaction(function () use ($draft, $optionLabel, $previewToken, $actor): ScheduleProposal {
            // Serialize concurrent submissions by this AO on transactional databases.
            $lockedActor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedActor->role !== 'ao') {
                throw new AuthorizationException('Hanya AO yang dapat mengajukan jadwal.');
            }

            $generated = $this->engine->generate($draft);
            $selected = collect($generated['options'])->firstWhere('option_label', $optionLabel);
            if (! $selected || ! hash_equals($this->token($draft, $selected), $previewToken)) {
                throw ValidationException::withMessages([
                    'preview_token' => 'Rekomendasi sudah berubah atau tidak tersedia. Perbarui opsi sebelum mengajukan.',
                ]);
            }
            if (count($selected['proposed_schedules_payload']) !== $draft['total_meetings']
                || (int) $selected['conflicts_count'] !== 0) {
                throw ValidationException::withMessages(['option_label' => 'Opsi tidak memenuhi seluruh pertemuan bebas bentrok.']);
            }

            $existing = ScheduleProposal::query()
                ->where('requested_by', $actor->getKey())
                ->where('status', 'pending')
                ->where('title', $draft['title'])
                ->where('start_date', $draft['start_date'])
                ->where('end_date', $draft['end_date'])
                ->with('options')
                ->get()
                ->first(fn (ScheduleProposal $proposal) => $this->isSamePending($proposal, $draft, $selected));
            if ($existing) {
                return $existing;
            }

            $proposal = ScheduleProposal::create([
                'requested_by' => $actor->getKey(),
                'title' => $draft['title'],
                'input_mode' => $draft['input_mode'],
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
                'preferred_instructor_id' => $draft['preferred_instructor_id'],
                'preferred_room_id' => $draft['preferred_room_id'],
                'required_competency_ids' => $draft['required_competency_ids'],
                'required_facility_codes' => $draft['required_facility_codes'],
                'ai_prompt' => $draft['ai_prompt'] ?? null,
                'status' => 'pending',
                'submitted_at' => Carbon::now('Asia/Jakarta'),
            ]);

            $selectedRecord = null;
            foreach ($generated['options'] as $option) {
                $record = $proposal->options()->create([
                    'option_label' => $option['option_label'],
                    'score' => $option['score'],
                    'conflicts_count' => $option['conflicts_count'],
                    'rationale' => $option['explanation'],
                    'proposed_schedules_payload' => $option['proposed_schedules_payload'],
                ]);
                if ($option['option_label'] === $optionLabel) {
                    $selectedRecord = $record;
                }
            }
            // The model also validates that this option belongs to this proposal.
            $proposal->selectOption($selectedRecord);

            $proposal->load('options');

            return $this->setSelectedOptionRelation($proposal);
        }, 3);
    }

    /** @return Collection<int, ScheduleProposal> */
    public function ownProposals(): Collection
    {
        $actor = $this->authenticatedAo();

        return ScheduleProposal::query()
            ->where('requested_by', $actor->getKey())
            ->with('options')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->each(fn (ScheduleProposal $proposal) => $this->setSelectedOptionRelation($proposal));
    }

    public function ownProposalDetail(int $proposalId): ScheduleProposal
    {
        $actor = $this->authenticatedAo();

        $proposal = ScheduleProposal::query()
            ->where('requested_by', $actor->getKey())
            ->with('options')
            ->findOrFail($proposalId);

        return $this->setSelectedOptionRelation($proposal);
    }

    public function ownOptionDetail(int $proposalId, int $optionId): ScheduleProposalOption
    {
        $actor = $this->authenticatedAo();
        // Ownership must be established from the proposal, not from the option ID alone.
        $proposal = ScheduleProposal::query()
            ->where('requested_by', $actor->getKey())
            ->findOrFail($proposalId);

        return $proposal->options()->findOrFail($optionId);
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

    private function token(array $draft, array $option): string
    {
        // Stable canonical inputs: normalize() has already ordered weekdays and IDs.
        return hash('sha256', json_encode([
            'draft' => $draft,
            'option_label' => $option['option_label'],
            'score' => $option['score'],
            'proposed_schedules_payload' => $option['proposed_schedules_payload'],
        ], JSON_THROW_ON_ERROR));
    }

    private function isSamePending(ScheduleProposal $proposal, array $draft, array $selected): bool
    {
        $option = $this->setSelectedOptionRelation($proposal)->selectedOption;
        if (! $option || $option->option_label !== $selected['option_label']
            || $option->proposed_schedules_payload != $selected['proposed_schedules_payload']) {
            return false;
        }

        $fields = [
            'title', 'input_mode', 'department', 'class_kind', 'program_id', 'curriculum_version_id',
            'student_count', 'start_date', 'end_date', 'total_meetings', 'session_duration_minutes',
            'frequency_per_week', 'preferred_weekdays', 'preferred_time_window',
            'preferred_instructor_id', 'preferred_room_id', 'required_competency_ids',
            'required_facility_codes',
        ];
        foreach ($fields as $field) {
            if ($proposal->{$field} != $draft[$field]) {
                return false;
            }
        }

        return $proposal->ai_prompt === ($draft['ai_prompt'] ?? null);
    }

    private function setSelectedOptionRelation(ScheduleProposal $proposal): ScheduleProposal
    {
        if (! $proposal->relationLoaded('options')) {
            $proposal->load('options');
        }

        $proposal->setRelation(
            'selectedOption',
            $proposal->options->firstWhere('id', $proposal->selected_option_id),
        );

        return $proposal;
    }
}
