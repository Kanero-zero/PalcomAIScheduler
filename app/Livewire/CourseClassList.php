<?php

namespace App\Livewire;

use App\Models\CourseClass;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class CourseClassList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $subject = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSubject(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'subject');
        $this->resetPage();
    }

    public function render(): View
    {
        $query = CourseClass::query()->withCount('schedules');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $classQuery) use ($search): void {
                $classQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%');
            });
        }

        if ($this->subject !== '') {
            $query->where('subject', $this->subject);
        }

        return view('livewire.course-class-list', [
            'classes' => $query->orderBy('name')->orderBy('id')->paginate(10),
            'subjects' => CourseClass::query()->distinct()->orderBy('subject')->pluck('subject'),
            'totalClasses' => CourseClass::query()->count(),
            'totalEnrollments' => (int) CourseClass::query()->sum('student_count'),
        ]);
    }
}
