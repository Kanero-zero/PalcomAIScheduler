<?php

namespace App\Livewire;

use App\Models\InstructorLeave;
use App\Services\Scheduling\ScheduleApprovalService;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ApprovalReview extends Component
{
    #[Locked]
    public ?int $selectedLeaveId = null;

    #[Locked]
    public ?int $selectedScheduleId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $result = null;

    public ?string $feedbackMessage = null;

    public function mount(): void
    {
        $defaultLeave = InstructorLeave::orderByDesc('date')->orderByDesc('id')->first();
        if ($defaultLeave) {
            $this->selectedLeaveId = $defaultLeave->id;
            $this->loadEvaluation();
        }
    }

    public function selectLeave(int $leaveId): void
    {
        $this->resetValidation();
        $this->feedbackMessage = null;
        $this->selectedScheduleId = null;
        $this->selectedLeaveId = $leaveId;
        $this->loadEvaluation();
    }

    public function selectSchedule(int $scheduleId): void
    {
        $this->resetValidation();
        $this->feedbackMessage = null;
        $this->loadEvaluation();

        if (! collect($this->result['affected_schedules'] ?? [])->contains('schedule_id', $scheduleId)) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal tidak termasuk kelas terdampak pengajuan izin ini.',
            ]);
        }

        $this->selectedScheduleId = $scheduleId;
    }

    public function loadEvaluation(): void
    {
        $this->resetValidation();

        if (! $this->selectedLeaveId) {
            $this->result = null;
            $this->selectedScheduleId = null;

            return;
        }

        $leave = InstructorLeave::with('instructor')->find($this->selectedLeaveId);
        if (! $leave) {
            $this->result = null;
            $this->selectedScheduleId = null;

            return;
        }

        $engine = app(SchedulingEngine::class);
        $evaluation = $engine->evaluateLeave($leave);
        $this->result = $evaluation->toArray();

        if (! collect($this->result['affected_schedules'])->contains('schedule_id', $this->selectedScheduleId)) {
            $this->selectedScheduleId = $this->result['affected_schedules'][0]['schedule_id'] ?? null;
        }
    }

    /**
     * Action untuk menyetujui instruktur pengganti pada jadwal kelas yang terdampak.
     *
     * @return array<string, mixed>
     */
    public function approveSubstitution(int $leaveId, int $scheduleId, int $replacementInstructorId, ?string $notes = null): array
    {
        Gate::authorize('manage-schedule-approval');
        $this->resetValidation();
        $this->feedbackMessage = null;
        $service = app(ScheduleApprovalService::class);
        try {
            $response = $service->approveSubstitution($leaveId, $scheduleId, $replacementInstructorId, $notes);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return ['success' => false];
        }

        $this->selectedLeaveId = $leaveId;
        $this->selectedScheduleId = $scheduleId;
        $this->loadEvaluation();
        $this->feedbackMessage = $response['message'];

        return $response;
    }

    /**
     * Action untuk menolak rekomendasi pengganti pada jadwal kelas yang terdampak.
     *
     * @return array<string, mixed>
     */
    public function rejectSubstitution(int $leaveId, int $scheduleId, string $rejectionReason): array
    {
        Gate::authorize('manage-schedule-approval');
        $this->resetValidation();
        $this->feedbackMessage = null;
        $service = app(ScheduleApprovalService::class);
        try {
            $response = $service->rejectSubstitution($leaveId, $scheduleId, $rejectionReason);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return ['success' => false];
        }

        $this->selectedLeaveId = $leaveId;
        $this->selectedScheduleId = $scheduleId;
        $this->loadEvaluation();
        $this->feedbackMessage = $response['message'];

        return $response;
    }

    public function render(): View
    {
        $leaves = InstructorLeave::with('instructor')->orderByDesc('date')->orderByDesc('id')->get();

        return view('livewire.approval-review', [
            'leaves' => $leaves,
        ]);
    }
}
