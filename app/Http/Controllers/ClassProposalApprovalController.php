<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleProposal;
use App\Services\Scheduling\ProposalApprovalConflictValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassProposalApprovalController extends Controller
{
    public function index(Request $request, ProposalApprovalConflictValidator $validator): View
    {
        Gate::authorize('manage-class-proposal-approval');
        $status = $this->statusFilter($request->query('status'));
        $proposals = $this->proposalQuery($status)->limit(50)->get();
        $selectedId = $request->integer('proposal');
        $selected = $proposals->firstWhere('id', $selectedId) ?? $proposals->first();
        $validation = $selected ? $this->validationFor($selected, $validator) : null;

        return view('pages.class-proposal-approvals', [
            'status' => $status,
            'proposals' => $proposals,
            'selected' => $selected,
            'selectedDetails' => $selected ? $this->proposalDetails($selected) : null,
            'validation' => $validation,
            'approvalToken' => Str::random(64),
            'rejectionToken' => Str::random(64),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('manage-class-proposal-approval');
        $status = $this->statusFilter($request->query('status'));
        $proposals = $this->proposalQuery($status)->limit(100)->get();

        return response()->json([
            'status' => $status,
            'proposals' => $proposals->map(fn (ScheduleProposal $proposal): array => $this->proposalSummary($proposal))->values(),
        ]);
    }

    public function show(ScheduleProposal $proposal, ProposalApprovalConflictValidator $validator): JsonResponse
    {
        Gate::authorize('manage-class-proposal-approval');
        $proposal->load($this->proposalRelations());

        return response()->json([
            'proposal' => $this->proposalDetails($proposal),
            'validation' => $this->validationFor($proposal, $validator),
        ]);
    }

    public function preview(ScheduleProposal $proposal, ProposalApprovalConflictValidator $validator): JsonResponse
    {
        Gate::authorize('manage-class-proposal-approval');
        $proposal->loadMissing('selectedOption');

        return response()->json($this->validationFor($proposal, $validator));
    }

    public function approve(
        Request $request,
        ScheduleProposal $proposal,
        ProposalApprovalConflictValidator $validator
    ): JsonResponse|RedirectResponse {
        Gate::authorize('manage-class-proposal-approval');
        $data = $request->validate([
            'idempotency_token' => ['required', 'string', 'min:16', 'max:64'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $proposal->loadMissing('selectedOption');
        $option = $proposal->selectedOption;
        if (! $option) {
            throw ValidationException::withMessages([
                'option' => 'Proposal belum memiliki opsi jadwal terpilih.',
            ]);
        }

        $decision = $validator->executeApprovalWithLock(
            $proposal,
            $option,
            $request->user(),
            $data['idempotency_token'],
            $data['reason'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Proposal disetujui. Seluruh pertemuan telah menjadi jadwal resmi.',
                'decision_id' => $decision->id,
                'status' => 'approved',
            ]);
        }

        return redirect()->route('class-proposal-approvals.index', [
            'status' => 'approved',
            'proposal' => $proposal->id,
        ])->with('success', 'Proposal disetujui. Seluruh pertemuan telah menjadi jadwal resmi.');
    }

    public function reject(
        Request $request,
        ScheduleProposal $proposal,
        ProposalApprovalConflictValidator $validator
    ): JsonResponse|RedirectResponse {
        Gate::authorize('manage-class-proposal-approval');
        $data = $request->validate([
            'idempotency_token' => ['required', 'string', 'min:16', 'max:64'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $decision = $validator->executeRejectionWithLock(
            $proposal,
            $request->user(),
            $data['idempotency_token'],
            $data['reason'],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Proposal ditolak tanpa membuat jadwal resmi.',
                'decision_id' => $decision->id,
                'status' => 'rejected',
                'reason' => $decision->reason,
            ]);
        }

        return redirect()->route('class-proposal-approvals.index', [
            'status' => 'rejected',
            'proposal' => $proposal->id,
        ])->with('success', 'Proposal ditolak tanpa membuat jadwal resmi.');
    }

    private function statusFilter(mixed $status): string
    {
        return in_array($status, ['pending', 'approved', 'rejected', 'reviewed'], true)
            ? $status
            : 'pending';
    }

    private function proposalQuery(string $status): Builder
    {
        return ScheduleProposal::query()
            ->when(
                $status === 'reviewed',
                fn ($query) => $query->whereIn('status', ['approved', 'rejected']),
                fn ($query) => $query->where('status', $status),
            )
            ->with($this->proposalRelations())
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');
    }

    /** @return list<string> */
    private function proposalRelations(): array
    {
        return [
            'requester:id,name,email',
            'selectedOption:id,proposal_id,option_label,proposed_schedules_payload,score,rationale',
            'latestDecision.approver:id,name',
            'officialSchedules.instructor:id,name',
            'officialSchedules.room:id,name',
        ];
    }

    /** @return array<string, mixed> */
    private function proposalSummary(ScheduleProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'requester' => $proposal->requester?->name,
            'submitted_at' => $proposal->submitted_at?->toIso8601String(),
            'department' => $proposal->department,
            'class_kind' => $proposal->class_kind,
            'student_count' => $proposal->student_count,
            'status' => $proposal->status,
            'decision_reason' => $proposal->latestDecision?->reason,
        ];
    }

    /** @return array<string, mixed> */
    private function proposalDetails(ScheduleProposal $proposal): array
    {
        $proposal->loadMissing($this->proposalRelations());
        $usesOfficialSchedules = $proposal->status === 'approved';
        $meetings = $usesOfficialSchedules
            ? $proposal->officialSchedules->map(fn (Schedule $schedule): array => [
                'sequence' => $schedule->meeting_sequence,
                'date' => $schedule->date->format('Y-m-d'),
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'instructor_id' => $schedule->instructor_id,
                'instructor_name' => $schedule->instructor?->name ?? 'Instruktur tidak tersedia',
                'room_id' => $schedule->room_id,
                'room_name' => $schedule->room?->name ?? 'Ruangan tidak tersedia',
                'schedule_id' => $schedule->id,
                'status' => $schedule->status,
            ])->values()->all()
            : $this->proposalMeetings($proposal);

        return array_merge($this->proposalSummary($proposal), [
            'start_date' => $proposal->start_date,
            'end_date' => $proposal->end_date,
            'total_meetings' => $proposal->total_meetings,
            'frequency_per_week' => $proposal->frequency_per_week,
            'session_duration_minutes' => $proposal->session_duration_minutes,
            'preferred_weekdays' => $proposal->preferred_weekdays ?? [],
            'preferred_time_window' => $proposal->preferred_time_window ?? [],
            'option_label' => $proposal->selectedOption?->option_label,
            'meetings' => $meetings,
            'meetings_source' => $usesOfficialSchedules ? 'official_schedules' : 'proposal_option',
            'official_schedule_count' => $proposal->officialSchedules->count(),
            'official_schedule_verified' => $usesOfficialSchedules
                && $proposal->officialSchedules->count() === $proposal->total_meetings,
            'decision' => $proposal->latestDecision ? [
                'decision' => $proposal->latestDecision->decision,
                'reason' => $proposal->latestDecision->reason,
                'round' => $proposal->latestDecision->round,
                'decided_at' => $proposal->latestDecision->decided_at?->toIso8601String(),
                'decided_by' => $proposal->latestDecision->approver?->name,
            ] : null,
        ]);
    }

    /** @return array{has_conflicts:bool,conflicts:array<int, array<string, mixed>>} */
    private function validationFor(
        ScheduleProposal $proposal,
        ProposalApprovalConflictValidator $validator
    ): array {
        if ($proposal->status === 'approved') {
            $proposal->loadMissing('officialSchedules');
            $actual = $proposal->officialSchedules->count();
            $expected = $proposal->total_meetings;

            return [
                'has_conflicts' => $actual !== $expected,
                'conflicts' => $actual === $expected ? [] : [[
                    'type' => 'official_schedule_integrity',
                    'date' => '',
                    'start_time' => '',
                    'end_time' => '',
                    'resource_id' => $proposal->id,
                    'resource_name' => 'Jadwal resmi',
                    'reason' => "Tabel schedules berisi {$actual} dari {$expected} pertemuan resmi untuk proposal ini.",
                ]],
                'mode' => 'official_schedule_integrity',
            ];
        }

        if ($proposal->status === 'rejected') {
            return ['has_conflicts' => false, 'conflicts' => [], 'mode' => 'not_applicable'];
        }

        $proposal->loadMissing('selectedOption');
        if (! $proposal->selectedOption) {
            return [
                'has_conflicts' => true,
                'conflicts' => [[
                    'type' => 'missing_selected_option',
                    'date' => '',
                    'start_time' => '',
                    'end_time' => '',
                    'resource_id' => 0,
                    'resource_name' => 'Proposal',
                    'reason' => 'Proposal belum memiliki opsi jadwal terpilih.',
                ]],
                'mode' => 'live_conflict_validation',
            ];
        }

        return array_merge(
            $validator->validateOption($proposal, $proposal->selectedOption, failClosed: false),
            ['mode' => 'live_conflict_validation'],
        );
    }

    /** @return list<array<string, mixed>> */
    private function proposalMeetings(ScheduleProposal $proposal): array
    {
        $slots = (array) ($proposal->selectedOption?->proposed_schedules_payload ?? []);
        $instructors = Instructor::whereIn('id', array_filter(array_column($slots, 'instructor_id')))
            ->pluck('name', 'id');
        $rooms = Room::whereIn('id', array_filter(array_column($slots, 'room_id')))
            ->pluck('name', 'id');

        return collect($slots)->map(function (array $slot, int $index) use ($instructors, $rooms): array {
            $instructorId = $slot['instructor_id'] ?? null;
            $roomId = $slot['room_id'] ?? null;

            return [
                'sequence' => $slot['meeting_sequence'] ?? $index + 1,
                'date' => $slot['date'] ?? null,
                'start_time' => $slot['start_time'] ?? null,
                'end_time' => $slot['end_time'] ?? null,
                'instructor_id' => $instructorId,
                'instructor_name' => $instructors[$instructorId] ?? ($slot['instructor_name'] ?? 'Belum ditentukan'),
                'room_id' => $roomId,
                'room_name' => $rooms[$roomId] ?? ($slot['room_name'] ?? 'Belum ditentukan'),
            ];
        })->values()->all();
    }
}
