<?php

namespace App\Livewire;

use App\Models\Room;
use App\Services\Room\RoomConflictGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class RoomList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $department = '';

    public string $status = '';

    // Modal form state
    public ?int $roomId = null;

    public string $name = '';

    public ?string $formDepartment = null;

    public string $capacity = '';

    public string $formStatus = 'available';

    public string $notes = '';

    public string $mode = 'create'; // 'create' | 'edit'

    public bool $isFormOpen = false;

    public bool $isDetailOpen = false;

    /**
     * @var array{id: int, name: string, department: string|null, capacity: int, status: string, notes: string|null}|null
     */
    public ?array $selectedRoom = null;

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

    public function openCreate(): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'));

            return;
        }

        $this->authorize('create', Room::class);

        $this->resetValidation();
        $this->resetErrorBag();
        $this->mode = 'create';
        $this->roomId = null;
        $this->name = '';
        $this->formDepartment = null;
        $this->capacity = '';
        $this->formStatus = 'available';
        $this->notes = '';
        $this->isFormOpen = true;

        $this->dispatch('open-room-form-modal', mode: 'create');
    }

    public function openEdit(int $id): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'));

            return;
        }

        $room = Room::findOrFail($id);
        $this->authorize('update', $room);

        $this->resetValidation();
        $this->resetErrorBag();
        $this->mode = 'edit';
        $this->roomId = $room->id;
        $this->name = $room->name;
        // Pada data legacy department=NULL, formDepartment tetap null agar radio tidak terpilih otomatis
        $this->formDepartment = $room->department ?: null;
        $this->capacity = (string) $room->capacity;
        $this->formStatus = $room->status;
        $this->notes = $room->notes ?? '';
        $this->isFormOpen = true;

        $this->dispatch('open-room-form-modal', mode: 'edit', room: [
            'id' => $room->id,
            'name' => $room->name,
            'department' => $room->department,
            'capacity' => $room->capacity,
            'status' => $room->status,
            'notes' => $room->notes,
        ]);
    }

    public function openDetail(int $id): void
    {
        $room = Room::findOrFail($id);

        $this->selectedRoom = [
            'id' => $room->id,
            'name' => $room->name,
            'department' => $room->department,
            'capacity' => $room->capacity,
            'status' => $room->status,
            'notes' => $room->notes,
        ];
        $this->isDetailOpen = true;

        $this->dispatch('open-room-detail-modal', room: $this->selectedRoom);
    }

    public function closeForm(): void
    {
        $this->isFormOpen = false;
        $this->resetValidation();
        $this->resetErrorBag();
        $this->dispatch('close-room-form-modal');
    }

    public function closeDetail(): void
    {
        $this->isDetailOpen = false;
        $this->dispatch('close-room-detail-modal');
    }

    public function save(RoomConflictGuard $guard): void
    {
        if (! auth()->check()) {
            $this->redirect(route('login'));

            return;
        }

        if ($this->mode === 'create') {
            $this->authorize('create', Room::class);
        } else {
            $roomToAuthorize = Room::findOrFail($this->roomId);
            $this->authorize('update', $roomToAuthorize);
        }

        // Auto-trim name dan notes
        $this->name = trim((string) $this->name);
        $this->notes = trim((string) $this->notes);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:1', 'max:150'],
            'formDepartment' => ['required', 'string', 'in:computer,speakout'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'formStatus' => ['required', 'string', 'in:available,maintenance,unavailable'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama ruangan wajib diisi.',
            'name.min' => 'Nama ruangan tidak boleh kosong.',
            'name.max' => 'Nama ruangan maksimal 150 karakter.',
            'formDepartment.required' => 'Bidang pembelajaran wajib dipilih.',
            'formDepartment.in' => 'Bidang pembelajaran harus salah satu dari: Komputer atau SpeakOut.',
            'capacity.required' => 'Kapasitas maksimal wajib diisi.',
            'capacity.integer' => 'Kapasitas harus berupa bilangan bulat positif.',
            'capacity.min' => 'Kapasitas minimal 1 siswa.',
            'capacity.max' => 'Kapasitas melebihi batas maksimal yang diizinkan.',
            'formStatus.required' => 'Status operasional wajib dipilih.',
            'formStatus.in' => 'Status operasional tidak valid.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ]);

        $data = [
            'name' => $this->name,
            'department' => $validated['formDepartment'],
            'capacity' => (int) $validated['capacity'],
            'status' => $validated['formStatus'],
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        if ($this->mode === 'create') {
            DB::transaction(function () use ($data): void {
                Room::create($data);
            });

            session()->flash('success', "Ruangan '{$data['name']}' berhasil ditambahkan.");
            $this->isFormOpen = false;
            $this->dispatch('close-room-form-modal');
            $this->resetPage();
        } else {
            DB::transaction(function () use ($data, $guard): void {
                $room = Room::lockForUpdate()->findOrFail($this->roomId);

                // Jalankan guard konflik jadwal resmi R19–R22
                $guard->validateRoomUpdate($room, $data);

                $room->update($data);
            });

            session()->flash('success', "Perubahan ruangan '{$data['name']}' berhasil disimpan.");
            $this->isFormOpen = false;
            $this->dispatch('close-room-form-modal');
        }
    }

    public function render(): View
    {
        $query = Room::query();

        $search = trim($this->search);
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($this->department === 'computer') {
            $query->where('department', 'computer');
        } elseif ($this->department === 'speakout') {
            $query->where('department', 'speakout');
        }
        // Jika department === '', tidak ada filter department, sehingga legacy department=NULL tetap tampil pada 'Semua'

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return view('livewire.room-list', [
            'rooms' => $query->orderBy('name')->orderBy('id')->paginate(10),
            'statuses' => Room::query()->distinct()->whereNotNull('status')->orderBy('status')->pluck('status'),
            'totalRooms' => Room::query()->count(),
        ]);
    }
}
