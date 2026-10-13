<div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 p-4 sm:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">Activity Log</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Jejak pengajuan izin, evaluasi, analisis AI, keputusan admin, dan pembaruan jadwal.</p>
        </div>
        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $backendReady ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300' }}">
            {{ $backendReady ? 'Terhubung ke Activity Log' : 'Menunggu Backend Activity Log' }}
        </span>
    </header>

    @if (! $backendReady)
        <section role="status" class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            <h2 class="text-sm font-semibold">Riwayat nyata belum tersedia</h2>
            <p class="mt-2 text-sm leading-6">Backend pencatatan aktivitas PR #16 belum tersedia di branch ini atau migrasinya belum diterapkan. Tidak ada data simulasi yang ditampilkan sebagai aktivitas sebenarnya.</p>
        </section>
    @else
        <section aria-label="Filter riwayat aktivitas" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Riwayat Aktivitas</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ count($activities) }} hasil (maksimal 50 aktivitas terbaru sesuai filter). Data diperbarui ketika halaman dimuat atau filter diubah.</p>
                </div>
                <button type="button" wire:click="resetFilters" class="text-sm font-semibold text-[#1565D8] hover:underline dark:text-blue-300">Reset filter</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                    Jenis Aktivitas
                    <select wire:model.live="type" aria-label="Jenis Aktivitas" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option value="all">Semua Aktivitas</option>
                        <option value="leave">Pengajuan Izin</option>
                        <option value="engine">Scheduling Engine</option>
                        <option value="ai">Analisis AI</option>
                        <option value="approval">Persetujuan</option>
                        <option value="schedule">Perubahan Jadwal</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                    Status
                    <select wire:model.live="state" aria-label="Status Aktivitas" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option value="all">Semua Status</option>
                        <option value="success">Selesai</option>
                        <option value="partial">Sebagian Selesai</option>
                        <option value="fallback">Fallback</option>
                        <option value="not_applicable">Tidak Diperlukan</option>
                        <option value="pending">Menunggu</option>
                        <option value="rejected">Ditolak</option>
                        <option value="info">Informasi</option>
                        <option value="warning">Peringatan</option>
                    </select>
                </label>
            </div>
        </section>

        <section aria-label="Timeline aktivitas" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            @if (count($activities) === 0)
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                    <p class="mt-3 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Tidak ada aktivitas untuk filter yang dipilih.</p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Catatan akan tampil ketika backend mencatat aktivitas baru.</p>
                </div>
            @else
                @php
                    $colors = [
                        'success' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                        'partial' => 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                        'fallback' => 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                        'rejected' => 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
                        'pending' => 'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
                    ];
                @endphp
                <ol class="space-y-0">
                    @foreach ($activities as $activity)
                        <li wire:key="activity-{{ $activity['id'] }}" class="relative border-l-2 border-zinc-200 pb-6 pl-6 last:border-transparent last:pb-0 dark:border-zinc-700">
                            <span class="absolute -left-[7px] top-1 size-3 rounded-full bg-[#1565D8] ring-4 ring-white dark:ring-zinc-900"></span>
                            <article class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-800 dark:bg-zinc-800/30">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-semibold text-[#1565D8] dark:text-blue-300">{{ $activity['type_label'] }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $colors[$activity['state']] ?? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ $activity['state_label'] }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $activity['at'] }}</span>
                                </div>
                                <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">{{ $activity['title'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $activity['description'] }}</p>
                                <div class="mt-3 flex flex-wrap gap-4 border-t border-zinc-200 pt-3 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                    <span><strong class="font-medium">Aktor:</strong> {{ $activity['actor'] }}</span>
                                    <span><strong class="font-medium">Referensi:</strong> {{ $activity['subject'] }}</span>
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
</div>
