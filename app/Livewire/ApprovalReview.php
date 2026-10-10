<?php

namespace App\Livewire;

use App\Models\InstructorLeave;
use App\Services\Scheduling\ScheduleApprovalService;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ApprovalReview extends Component
{
    public ?int $selectedLeaveId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    public ?string $feedbackMessage = null;

    public function mount(): void
    {
        $defaultLeave = InstructorLeave::with('instructor')->first();
        if ($defaultLeave) {
            $this->selectedLeaveId = $defaultLeave->id;
            $this->loadEvaluation();
        }
    }

    public function selectLeave(int $leaveId): void
    {
        $this->selectedLeaveId = $leaveId;
        $this->loadEvaluation();
    }

    public function loadEvaluation(): void
    {
        $this->feedbackMessage = null;

        if (! $this->selectedLeaveId) {
            $this->result = null;

            return;
        }

        $leave = InstructorLeave::with('instructor')->find($this->selectedLeaveId);
        if (! $leave) {
            $this->result = null;

            return;
        }

        $engine = app(SchedulingEngine::class);
        $evaluation = $engine->evaluateLeave($leave);
        $this->result = $evaluation->toArray();
    }

    /**
     * Action untuk menyetujui instruktur pengganti pada jadwal kelas yang terdampak.
     *
     * @return array<string, mixed>
     */
    public function approveSubstitution(int $leaveId, int $scheduleId, int $replacementInstructorId, ?string $notes = null): array
    {
        $service = app(ScheduleApprovalService::class);
        $response = $service->approveSubstitution($leaveId, $scheduleId, $replacementInstructorId, $notes);

        $this->feedbackMessage = $response['message'];
        $this->loadEvaluation();

        return $response;
    }

    /**
     * Action untuk menolak rekomendasi pengganti pada jadwal kelas yang terdampak.
     *
     * @return array<string, mixed>
     */
    public function rejectSubstitution(int $leaveId, int $scheduleId, string $rejectionReason): array
    {
        $service = app(ScheduleApprovalService::class);
        $response = $service->rejectSubstitution($leaveId, $scheduleId, $rejectionReason);

        $this->feedbackMessage = $response['message'];
        $this->loadEvaluation();

        return $response;
    }

    public function render(): View
    {
        $leaves = InstructorLeave::with('instructor')->orderByDesc('date')->get();

        return view('livewire.approval-review', [
            'leaves' => $leaves,
        ]);
    }
}
