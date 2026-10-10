<?php

namespace App\Livewire;

use App\Models\Instructor;
use App\Models\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class ScheduleList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $date = '';

    public string $instructorId = '';

    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDate(): void
    {
        $this->resetPage();
    }

    public function updatedInstructorId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'date', 'instructorId', 'status');
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Schedule::query()->with([
            'courseClass:id,name,subject,student_count',
            'instructor:id,name',
            'room:id,name,capacity',
        ]);

        $search = trim($this->search);
        if ($search !== '') {
            $query->whereHas('courseClass', function (Builder $classQuery) use ($search): void {
                $classQuery->where(function (Builder $textQuery) use ($search): void {
                    $textQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%');
                });
            });
        }

        if ($this->date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1) {
            $query->whereDate('date', $this->date);
        }

        if ($this->instructorId !== '' && ctype_digit($this->instructorId)) {
            $query->where('instructor_id', (int) $this->instructorId);
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return view('livewire.schedule-list', [
            'schedules' => $query->orderBy('date')->orderBy('start_time')->orderBy('id')->paginate(10),
            'instructors' => Instructor::query()->whereHas('schedules')->orderBy('name')->get(['id', 'name']),
            'statuses' => Schedule::query()->distinct()->orderBy('status')->pluck('status'),
            'totalSchedules' => Schedule::query()->count(),
        ]);
    }
}
