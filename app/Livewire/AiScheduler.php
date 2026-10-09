<?php

namespace App\Livewire;

use App\Models\InstructorLeave;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiScheduler extends Component
{
    public ?int $selectedLeaveId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    public function mount(): void
    {
        $defaultLeave = InstructorLeave::with('instructor')->first();
        if ($defaultLeave) {
            $this->selectedLeaveId = $defaultLeave->id;
            $this->runScheduler();
        }
    }

    public function selectLeave(int $leaveId): void
    {
        $this->selectedLeaveId = $leaveId;
        $this->runScheduler();
    }

    public function runScheduler(): void
    {
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

    public function render(): View
    {
        $leaves = InstructorLeave::with('instructor')->orderByDesc('date')->get();

        return view('livewire.ai-scheduler', [
            'leaves' => $leaves,
        ]);
    }
}
