<?php

namespace App\Livewire;

use App\Models\Instructor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Component;
use Livewire\WithPagination;

class InstructorList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $department = '';

    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartment(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'department', 'status');
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Instructor::query()->with([
            'skills' => function (HasMany $skillQuery): void {
                $skillQuery->orderBy('skill')->orderBy('id');
            },
            'availabilities' => function (HasMany $availabilityQuery): void {
                $availabilityQuery->orderBy('day')->orderBy('start_time');
            },
        ]);

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $instructorQuery) use ($search): void {
                $instructorQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('skills', function (Builder $skillQuery) use ($search): void {
                        $skillQuery->where('skill', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($this->department !== '' && $this->department !== 'all') {
            $query->where('department', $this->department);
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return view('livewire.instructor-list', [
            'instructors' => $query->orderBy('name')->orderBy('id')->paginate(10),
            'statuses' => Instructor::query()->distinct()->whereNotNull('status')->orderBy('status')->pluck('status'),
            'totalInstructors' => Instructor::query()->count(),
        ]);
    }
}
