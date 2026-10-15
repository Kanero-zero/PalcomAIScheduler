<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">Data Instruktur</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Daftar instruktur LKP PalComTech beserta kompetensi dan tingkat keahlian materi.</p>
        </div>
        @can('create', App\Models\Instructor::class)
            <a href="{{ route('instructors.create') }}" wire:navigate class="inline-flex items-center rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0A3D91]">+ Tambah Instruktur</a>
        @endcan
    </header>

    <section aria-label="Ringkasan instruktur" class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total instruktur tersimpan</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalInstructors, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Hasil sesuai filter</p>
            <p class="mt-1 text-2xl font-bold text-[#1565D8] dark:text-blue-300">{{ number_format($instructors->total(), 0, ',', '.') }}</p>
        </div>
    </section>

    <section aria-label="Filter instruktur" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Cari dan Filter Instruktur</h2>
            <button type="button" wire:click="resetFilters" class="rounded-lg px-3 py-2 text-sm font-medium text-[#1565D8] hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/30">Reset filter</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                Nama atau kompetensi
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari instruktur / materi" maxlength="100" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
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
                Status instruktur
                <select wire:model.live="status" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $availableStatus)
                        <option value="{{ $availableStatus }}">{{ match ($availableStatus) { 'active' => 'Aktif', 'inactive' => 'Nonaktif', default => \Illuminate\Support\Str::headline($availableStatus) } }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section aria-label="Daftar instruktur" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-4 sm:px-6 dark:border-zinc-800">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Daftar Instruktur dan Kompetensi</h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Data instruktur terkelola, kompetensi terdaftar, dan jadwal ketersediaan mengajar mingguan.</p>
        </div>
        @if ($instructors->isEmpty())
            <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
                <flux:icon name="users" class="size-8 text-[#1565D8]" />
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $totalInstructors === 0 ? 'Belum ada data instruktur.' : 'Tidak ada instruktur yang sesuai dengan filter.' }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $totalInstructors === 0 ? 'Data instruktur akan muncul setelah tersedia.' : 'Coba ubah pencarian atau reset filter.' }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full table-fixed min-w-[740px] text-left text-sm lg:min-w-0">
                    <colgroup>
                        <col class="w-[15%]" />
                        <col class="w-[11%]" />
                        <col class="w-[31%]" />
                        <col class="w-[14%]" />
                        <col class="w-[9%]" />
                        <col class="w-[20%]" />
                    </colgroup>
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th scope="col" class="px-2 py-3 font-semibold sm:px-3">Instruktur</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Bidang</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Kompetensi &amp; tingkat keahlian</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Ketersediaan</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-2 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($instructors as $instructor)
                            <tr wire:key="instructor-{{ $instructor->id }}" class="text-zinc-700 dark:text-zinc-200">
                                <td class="break-words px-2 py-3 font-semibold text-zinc-900 sm:px-3 dark:text-white">{{ $instructor->name }}</td>
                                <td class="px-2 py-3 align-top">
                                    @if ($instructor->department === 'computer')
                                        <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-900/40 dark:text-blue-300">Komputer</span>
                                    @elseif ($instructor->department === 'speakout')
                                        <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 dark:bg-purple-900/40 dark:text-purple-300">SpeakOut</span>
                                    @else
                                        <span class="text-xs text-amber-700 dark:text-amber-300">Belum tercatat</span>
                                    @endif
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <div class="flex min-w-0 flex-wrap gap-1.5">
                                        @forelse ($instructor->skills as $skill)
                                            <span class="inline-flex max-w-full flex-wrap items-center gap-1 break-words rounded-lg border border-blue-100 bg-blue-50 px-2 py-1 text-xs text-zinc-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-zinc-200">
                                                <span class="min-w-0 break-words font-semibold">{{ $skill->skill }}</span>
                                                <span class="text-zinc-500 dark:text-zinc-400">· {{ match ($skill->level) { 'beginner' => 'Pemula', 'intermediate' => 'Menengah', 'advanced' => 'Mahir', default => \Illuminate\Support\Str::headline($skill->level) } }}</span>
                                            </span>
                                        @empty
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">Kompetensi belum tercatat</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    @if ($instructor->availabilities->isNotEmpty())
                                        @php
                                            $activeDays = $instructor->availabilities->pluck('day')->unique()->values();
                                        @endphp
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($activeDays as $day)
                                                <span class="rounded bg-zinc-100 px-1.5 py-0.5 text-[11px] font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $day }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-amber-700 dark:text-amber-300">Belum tercatat</span>
                                    @endif
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $instructor->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                        {{ match ($instructor->status) { 'active' => 'Aktif', 'inactive' => 'Nonaktif', default => \Illuminate\Support\Str::headline($instructor->status) } }}
                                    </span>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <div x-data>
                                            <button type="button" @click="$refs.detailDialog.showModal()" aria-label="Lihat detail instruktur {{ $instructor->name }}" class="inline-flex whitespace-nowrap items-center rounded-lg border border-zinc-300 px-2 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Lihat Detail</button>
                                            <dialog x-ref="detailDialog" aria-labelledby="instructor-detail-title-{{ $instructor->id }}" class="max-h-[85vh] w-[min(94vw,34rem)] overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-0 text-left text-zinc-900 shadow-2xl backdrop:bg-zinc-900/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                                                    <h3 id="instructor-detail-title-{{ $instructor->id }}" class="text-lg font-bold">Detail Instruktur — {{ $instructor->name }}</h3>
                                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Data yang benar-benar sudah tercatat di sistem.</p>
                                                </div>
                                                <dl class="space-y-4 px-5 py-5 text-sm">
                                                    <div><dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Nama instruktur</dt><dd class="mt-1 font-semibold">{{ $instructor->name }}</dd></div>
                                                    <div><dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Status</dt><dd class="mt-1">{{ match ($instructor->status) { 'active' => 'Aktif', 'inactive' => 'Nonaktif', default => \Illuminate\Support\Str::headline($instructor->status) } }}</dd></div>
                                                    <div>
                                                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Bidang pembelajaran</dt>
                                                        <dd class="mt-1">
                                                            @if ($instructor->department === 'computer')
                                                                <span class="font-semibold text-blue-700 dark:text-blue-300">Komputer</span>
                                                            @elseif ($instructor->department === 'speakout')
                                                                <span class="font-semibold text-purple-700 dark:text-purple-300">SpeakOut</span>
                                                            @else
                                                                <span class="text-amber-700 dark:text-amber-300">Belum tercatat</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Kompetensi &amp; tingkat penguasaan</dt>
                                                        <dd class="mt-2 flex flex-wrap gap-2">
                                                            @forelse ($instructor->skills as $skill)
                                                                <span class="rounded-lg border border-blue-100 bg-blue-50 px-2.5 py-1 text-xs dark:border-blue-900 dark:bg-blue-950/40">{{ $skill->skill }} · {{ match ($skill->level) { 'beginner' => 'Dasar', 'intermediate' => 'Menengah', 'advanced' => 'Mahir', default => \Illuminate\Support\Str::headline($skill->level) } }}</span>
                                                            @empty
                                                                <span class="text-xs text-zinc-500">Belum ada kompetensi tersimpan</span>
                                                            @endforelse
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Ketersediaan mengajar</dt>
                                                        <dd class="mt-1">
                                                            @if ($instructor->availabilities->isNotEmpty())
                                                                <ul class="space-y-1 text-xs">
                                                                    @foreach ($instructor->availabilities as $slot)
                                                                        <li class="flex items-center gap-2">
                                                                            <span class="w-16 font-medium text-zinc-700 dark:text-zinc-300">{{ $slot->day }}</span>
                                                                            <span class="text-zinc-500 dark:text-zinc-400">{{ $slot->start_time }} – {{ $slot->end_time }}</span>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @else
                                                                <span class="text-amber-700 dark:text-amber-300">Belum tercatat</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Catatan</dt>
                                                        <dd class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $instructor->notes ?: 'Belum tercatat' }}</dd>
                                                    </div>
                                                </dl>
                                                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                                                    <form method="dialog"><button type="submit" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Tutup</button></form>
                                                    @can('update', $instructor)
                                                        <a href="{{ route('instructors.edit', $instructor) }}" wire:navigate class="rounded-lg bg-[#1565D8] px-3 py-2 text-sm font-semibold text-white hover:bg-[#0A3D91]">Edit Instruktur</a>
                                                    @endcan
                                                </div>
                                            </dialog>
                                        </div>
                                        @can('update', $instructor)
                                            <a href="{{ route('instructors.edit', $instructor) }}" wire:navigate aria-label="Edit instruktur {{ $instructor->name }}" class="inline-flex items-center rounded-lg border border-blue-200 px-2 py-1.5 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 dark:border-blue-900 dark:hover:bg-blue-950/30">Edit</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-4 py-4 sm:px-6 dark:border-zinc-800">{{ $instructors->links() }}</div>
        @endif
    </section>
</div>
