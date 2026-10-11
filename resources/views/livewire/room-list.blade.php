<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">Ruangan &amp; Lab</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Data kapasitas dan status operasional ruangan LKP PalComTech.</p>
        </div>
        <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-[#1565D8] dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">Tampilan hanya baca</span>
    </header>

    <section aria-label="Ringkasan ruangan" class="grid gap-3 sm:grid-cols-2">
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
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                Nama ruangan atau lab
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama ruangan" maxlength="100" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
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
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Daftar Ruangan &amp; Kapasitas</h2>
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
                <table class="w-full min-w-[580px] text-left text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold sm:px-6">Ruangan / Lab</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Kapasitas</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status operasional</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($rooms as $room)
                            <tr wire:key="room-{{ $room->id }}" class="text-zinc-700 dark:text-zinc-200">
                                <td class="px-4 py-4 font-semibold text-zinc-900 sm:px-6 dark:text-white">{{ $room->name }}</td>
                                <td class="px-4 py-4">{{ number_format($room->capacity, 0, ',', '.') }} orang</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $room->status === 'available' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                        {{ match ($room->status) { 'available' => 'Operasional', 'maintenance' => 'Perawatan', 'unavailable' => 'Tidak operasional', default => \Illuminate\Support\Str::headline($room->status) } }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-4 py-4 sm:px-6 dark:border-zinc-800">{{ $rooms->links() }}</div>
        @endif
    </section>
</div>
