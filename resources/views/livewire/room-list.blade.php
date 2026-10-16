@php
    $user = auth()->user();
    $canManageRooms = $user?->isAdmin() === true || $user?->isBaak() === true;
@endphp

<div x-data="palcomRoomPreview()" class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">Data Ruangan &amp; Lab</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Data kapasitas dan status operasional ruangan LKP PalComTech.</p>
        </div>
        @if ($canManageRooms)
            <button type="button" wire:click="openCreate" @click="openCreate()" class="inline-flex items-center rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0A3D91]">+ Tambah Ruangan</button>
        @endif
    </header>

    @if (session()->has('success'))
        <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <section aria-label="Ringkasan ruangan" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total ruangan tersimpan</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalRooms, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Hasil sesuai filter</p>
            <p class="mt-1 text-2xl font-bold text-[#1565D8] dark:text-blue-300">{{ number_format($rooms->total(), 0, ',', '.') }}</p>
        </div>
    </section>

    <section aria-label="Filter ruangan" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Cari dan Filter Ruangan</h2>
            <button type="button" wire:click="resetFilters" class="rounded-lg px-3 py-2 text-sm font-medium text-[#1565D8] hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/30">Reset filter</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                Nama ruangan atau lab
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama ruangan" maxlength="100" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                Bidang pembelajaran
                <select wire:model.live="department" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    <option value="">Semua bidang</option>
                    <option value="computer">Komputer</option>
                    <option value="speakout">SpeakOut</option>
                </select>
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                Status operasional
                <select wire:model.live="status" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $availableStatus)
                        <option value="{{ $availableStatus }}">{{ match ($availableStatus) { 'available' => 'Operasional', 'maintenance' => 'Perawatan', 'unavailable' => 'Tidak operasional', default => \Illuminate\Support\Str::headline($availableStatus) } }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section aria-label="Daftar ruangan" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-4 sm:px-6 dark:border-zinc-800">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Daftar Ruangan &amp; Lab</h2>
            <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">Bidang pembelajaran dan catatan data lama belum tersimpan di database. Label “Belum tercatat” bukan berarti ruangan tidak tersedia.</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Status operasional bukan jaminan ruangan kosong pada waktu tertentu. Periksa jadwal mengajar untuk melihat penggunaan ruangan.</p>
        </div>
        @if ($rooms->isEmpty())
            <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
                <flux:icon name="building-office-2" class="size-8 text-[#1565D8]" />
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $totalRooms === 0 ? 'Belum ada data ruangan.' : 'Tidak ada ruangan yang sesuai dengan filter.' }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $totalRooms === 0 ? 'Data ruangan akan muncul setelah tersedia.' : 'Coba ubah pencarian atau reset filter.' }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] table-fixed text-left text-sm lg:min-w-0">
                    <colgroup><col class="w-[18%]" /><col class="w-[13%]" /><col class="w-[11%]" /><col class="w-[16%]" /><col class="w-[16%]" /><col class="w-[26%]" /></colgroup>
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th scope="col" class="px-2 py-3 font-semibold sm:px-3">Nama Ruangan</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Bidang</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Kapasitas</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Catatan</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($rooms as $room)
                            <tr wire:key="room-{{ $room->id }}" class="text-zinc-700 dark:text-zinc-200">
                                <td class="break-words px-2 py-3 font-semibold text-zinc-900 sm:px-3 dark:text-white">{{ $room->name }}</td>
                                <td class="px-2 py-3 align-top text-xs {{ $room->department ? 'font-medium text-zinc-900 dark:text-zinc-100' : 'text-amber-700 dark:text-amber-300' }}">
                                    {{ $room->department ? ($room->department === 'computer' ? 'Komputer' : 'SpeakOut') : 'Belum tercatat' }}
                                </td>
                                <td class="px-2 py-3 align-top">{{ number_format($room->capacity, 0, ',', '.') }} orang</td>
                                <td class="px-2 py-3 align-top">
                                    <span aria-label="Status {{ $room->name }}: {{ match ($room->status) { 'available' => 'Operasional', 'maintenance' => 'Perawatan', 'unavailable' => 'Tidak operasional', default => \Illuminate\Support\Str::headline($room->status) } }}" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ match ($room->status) { 'available' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300', 'maintenance' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300', default => 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300' } }}">
                                        {{ match ($room->status) { 'available' => 'Operasional', 'maintenance' => 'Perawatan', 'unavailable' => 'Tidak operasional', default => \Illuminate\Support\Str::headline($room->status) } }}
                                    </span>
                                </td>
                                <td class="break-words px-2 py-3 align-top text-xs {{ !empty(trim($room->notes ?? '')) ? 'text-zinc-800 dark:text-zinc-200' : 'text-amber-700 dark:text-amber-300' }}">
                                    {{ !empty(trim($room->notes ?? '')) ? $room->notes : 'Belum ada catatan' }}
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <button type="button" wire:click="openDetail({{ $room->id }})" @click="openDetail(@js(['id' => $room->id, 'name' => $room->name, 'department' => $room->department, 'capacity' => $room->capacity, 'status' => $room->status, 'notes' => $room->notes]))" class="whitespace-nowrap rounded-lg border border-zinc-300 px-2 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200">Lihat Detail</button>
                                        @if ($canManageRooms)
                                            <button type="button" wire:click="openEdit({{ $room->id }})" @click="openEdit(@js(['id' => $room->id, 'name' => $room->name, 'department' => $room->department, 'capacity' => $room->capacity, 'status' => $room->status, 'notes' => $room->notes]))" class="rounded-lg border border-blue-200 px-2 py-1.5 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300">Edit</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-4 py-4 sm:px-6 dark:border-zinc-800">{{ $rooms->links() }}</div>
        @endif
    </section>

    @if ($canManageRooms)
        @include('livewire.partials.room-preview-modals')
    @else
        <dialog x-ref="roomDetailDialog" @cancel="$wire.closeDetail()" aria-labelledby="room-detail-heading" class="max-h-[85vh] w-[min(94vw,34rem)] overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-0 text-left text-zinc-900 shadow-2xl backdrop:bg-zinc-900/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <div>
                    <h2 id="room-detail-heading" class="text-lg font-bold" x-text="'Detail Ruangan — ' + (selected?.name ?? '')">Detail Ruangan — {{ $selectedRoom['name'] ?? '' }}</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Data yang benar-benar tercatat di sistem.</p>
                </div>
                <button type="button" @click="closeDetail()" wire:click="closeDetail" aria-label="Tutup detail ruangan" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">✕</button>
            </div>
            <dl class="grid gap-5 px-5 py-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold text-zinc-500">Nama ruangan</dt>
                    <dd class="mt-1 break-words font-semibold" x-text="selected?.name ?? '—'">{{ $selectedRoom['name'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-zinc-500">Status operasional</dt>
                    <dd class="mt-1" x-text="statusLabel(selected?.status)">
                        {{ match ($selectedRoom['status'] ?? '') { 'available' => 'Operasional', 'maintenance' => 'Perawatan', 'unavailable' => 'Tidak operasional', default => 'Status tidak dikenal' } }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-zinc-500">Bidang pembelajaran</dt>
                    <dd class="mt-1" :class="selected?.department ? 'font-medium text-zinc-900 dark:text-zinc-100' : 'text-amber-700 dark:text-amber-300'" x-text="selected?.department ? (selected.department === 'computer' ? 'Komputer' : 'SpeakOut') : 'Belum tercatat'">
                        {{ !empty($selectedRoom['department']) ? ($selectedRoom['department'] === 'computer' ? 'Komputer' : 'SpeakOut') : 'Belum tercatat' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-zinc-500">Kapasitas maksimal</dt>
                    <dd class="mt-1"><span x-text="selected?.capacity ?? '—'">{{ $selectedRoom['capacity'] ?? '—' }}</span> siswa</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold text-zinc-500">Catatan</dt>
                    <dd class="mt-1" :class="selected?.notes && selected.notes.trim() !== '' ? 'text-zinc-800 dark:text-zinc-200' : 'text-amber-700 dark:text-amber-300'" x-text="selected?.notes && selected.notes.trim() !== '' ? selected.notes : 'Belum ada catatan'">
                        {{ !empty(trim($selectedRoom['notes'] ?? '')) ? $selectedRoom['notes'] : 'Belum ada catatan' }}
                    </dd>
                </div>
            </dl>
            <footer class="flex justify-end border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <button type="button" @click="closeDetail()" wire:click="closeDetail" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200">Tutup</button>
            </footer>
        </dialog>
    @endif
</div>
