<?php

namespace App\Livewire;

use App\Models\Room;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class RoomList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Room::query();

        $search = trim($this->search);
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return view('livewire.room-list', [
            'rooms' => $query->orderBy('name')->orderBy('id')->paginate(10),
            'statuses' => Room::query()->distinct()->orderBy('status')->pluck('status'),
            'totalRooms' => Room::query()->count(),
        ]);
    }
}
