<div class="flex min-w-0 flex-col gap-6">
    <section aria-label="Ringkasan jadwal mengajar" class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Total jadwal tersimpan</p>
            <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totalSchedules, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Sesi bulan yang ditampilkan</p>
            <p class="mt-1 text-2xl font-semibold text-[#1565D8] dark:text-blue-300">{{ number_format($monthlyScheduleCount, 0, ',', '.') }}</p>
        </div>
    </section>

    <div class="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-3">
        <section aria-label="Kalender mengajar" class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 xl:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Kalender mengajar</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Pilih tanggal untuk melihat jadwal kelas yang tersimpan.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="previousMonth" aria-label="Bulan sebelumnya" class="rounded-lg border border-zinc-200 p-2 text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"><flux:icon name="chevron-left" class="size-4" /></button>
                    <span class="min-w-28 text-center text-sm font-semibold capitalize text-zinc-900 dark:text-white">{{ $monthLabel }}</span>
                    <button type="button" wire:click="nextMonth" aria-label="Bulan berikutnya" class="rounded-lg border border-zinc-200 p-2 text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"><flux:icon name="chevron-right" class="size-4" /></button>
                    <button type="button" wire:click="goToToday" class="rounded-lg px-2 py-2 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/30">Hari ini</button>
                </div>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)
                    <span class="py-2">{{ $weekday }}</span>
                @endforeach
            </div>
            <div class="grid grid-cols-7 gap-1">
                @foreach ($calendarDays as $day)
                    <button
                        type="button"
                        wire:key="calendar-{{ $day['date'] }}"
                        wire:click="selectDate('{{ $day['date'] }}')"
                        @disabled(! $day['inMonth'])
                        aria-label="{{ $day['date'] }}: {{ $day['count'] }} sesi"
                        aria-pressed="{{ $selectedDate === $day['date'] ? 'true' : 'false' }}"
                        class="flex min-h-16 flex-col items-center justify-start gap-1 rounded-xl border p-2 text-sm transition-colors sm:min-h-18 {{ ! $day['inMonth'] ? 'cursor-default border-transparent text-zinc-300 dark:text-zinc-700' : ($selectedDate === $day['date'] ? 'border-[#1565D8] bg-blue-50 font-semibold text-[#0A3D91] dark:bg-blue-950/50 dark:text-blue-200' : 'border-transparent text-zinc-800 hover:border-blue-200 hover:bg-blue-50/50 dark:text-zinc-200 dark:hover:bg-zinc-800') }}"
                    >
                        <span class="{{ $day['isToday'] && $selectedDate !== $day['date'] ? 'rounded-full bg-blue-100 px-1.5 text-[#1565D8] dark:bg-blue-950 dark:text-blue-300' : '' }}">{{ $day['day'] }}</span>
                        @if ($day['inMonth'] && $day['count'] > 0)
                            <span class="rounded-full bg-[#1565D8] px-1.5 py-0.5 text-[10px] font-medium leading-none text-white" aria-hidden="true">{{ $day['count'] }} sesi</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">Angka pada tanggal menunjukkan jumlah sesi yang tercatat, bukan jumlah ruangan kosong.</p>
        </section>

        <section aria-label="Agenda tanggal terpilih" class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-800">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Agenda kelas</h2>
                <p class="mt-1 text-sm text-[#1565D8] dark:text-blue-300">{{ $selectedDateLabel }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $daySchedules->count() }} sesi tercatat pada tanggal ini</p>
            </div>
            @forelse ($daySchedules as $schedule)
                <article wire:key="daily-schedule-{{ $schedule->id }}" class="border-b border-zinc-100 p-5 last:border-b-0 dark:border-zinc-800">
                    <p class="text-xs font-semibold text-[#1565D8] dark:text-blue-300">{{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }} WIB</p>
                    <h3 class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $schedule->courseClass?->name ?? 'Kelas tidak tersedia' }}</h3>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $schedule->courseClass?->subject ?? 'Materi tidak tersedia' }}</p>
                    <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">{{ $schedule->instructor?->name ?? 'Instruktur belum tersedia' }} · {{ $schedule->room?->name ?? 'Ruangan belum tersedia' }}</p>
                    <span class="mt-2 inline-flex rounded-full bg-zinc-100 px-2 py-1 text-[11px] font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ match ($schedule->status) { 'scheduled' => 'Terjadwal', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', default => \Illuminate\Support\Str::headline($schedule->status) } }}</span>
                </article>
            @empty
                <div class="p-6 text-center text-sm text-zinc-500 dark:text-zinc-400">Tidak ada jadwal pada tanggal ini.</div>
            @endforelse
            <div class="border-t border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                <a href="{{ route('schedules.index') }}" wire:navigate class="text-xs font-semibold text-[#1565D8] hover:underline dark:text-blue-300">Lihat seluruh jadwal →</a>
            </div>
        </section>
    </div>

    <div class="grid min-w-0 gap-6 lg:grid-cols-2">
        <section aria-labelledby="dashboard-leave-summary" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="dashboard-leave-summary" class="font-semibold text-zinc-900 dark:text-white">Status pengajuan izin</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $totalLeaves }} pengajuan tercatat di database</p>
                </div>
                <a href="{{ route('instructor-leaves.index') }}" wire:navigate class="text-xs font-semibold text-[#1565D8] hover:underline dark:text-blue-300">Buka pengajuan →</a>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-amber-50 px-2 py-4 dark:bg-amber-950/30"><p class="text-2xl font-bold text-amber-800 dark:text-amber-200">{{ (int) ($leaveCounts['pending'] ?? 0) }}</p><p class="text-xs text-amber-700 dark:text-amber-300">Menunggu</p></div>
                <div class="rounded-xl bg-emerald-50 px-2 py-4 dark:bg-emerald-950/30"><p class="text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ (int) ($leaveCounts['approved'] ?? 0) }}</p><p class="text-xs text-emerald-700 dark:text-emerald-300">Disetujui</p></div>
                <div class="rounded-xl bg-rose-50 px-2 py-4 dark:bg-rose-950/30"><p class="text-2xl font-bold text-rose-800 dark:text-rose-200">{{ (int) ($leaveCounts['rejected'] ?? 0) }}</p><p class="text-xs text-rose-700 dark:text-rose-300">Ditolak</p></div>
            </div>
            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Status izin berbeda dari keputusan penggantian untuk setiap jadwal.</p>
        </section>
        <section aria-labelledby="dashboard-substitution-summary" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="dashboard-substitution-summary" class="font-semibold text-zinc-900 dark:text-white">Keputusan penggantian</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Status persetujuan untuk jadwal terdampak</p>
                </div>
                <a href="{{ route('approval-review') }}" wire:navigate class="text-xs font-semibold text-[#1565D8] hover:underline dark:text-blue-300">Tinjau keputusan →</a>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-amber-50 px-2 py-4 dark:bg-amber-950/30"><p class="text-2xl font-bold text-amber-800 dark:text-amber-200">{{ (int) ($substitutionCounts['pending'] ?? 0) }}</p><p class="text-xs text-amber-700 dark:text-amber-300">Menunggu</p></div>
                <div class="rounded-xl bg-emerald-50 px-2 py-4 dark:bg-emerald-950/30"><p class="text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ (int) ($substitutionCounts['approved'] ?? 0) }}</p><p class="text-xs text-emerald-700 dark:text-emerald-300">Disetujui</p></div>
                <div class="rounded-xl bg-rose-50 px-2 py-4 dark:bg-rose-950/30"><p class="text-2xl font-bold text-rose-800 dark:text-rose-200">{{ (int) ($substitutionCounts['rejected'] ?? 0) }}</p><p class="text-xs text-rose-700 dark:text-rose-300">Ditolak</p></div>
            </div>
            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Dashboard hanya menampilkan keputusan yang telah tersimpan, bukan menjalankan persetujuan.</p>
        </section>
    </div>

    <section aria-label="Rekomendasi AI" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-blue-100 bg-blue-50/60 p-5 dark:border-blue-900 dark:bg-blue-950/20">
        <div class="max-w-2xl">
            <h2 class="text-sm font-semibold text-[#0A3D91] dark:text-blue-200">Rekomendasi pengganti AI</h2>
            <p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-300">Ringkasan hasil analisis AI belum tersedia sebagai data tersimpan pada Dashboard. Buka AI Scheduler untuk menjalankan dan melihat rekomendasi sesuai pengajuan izin.</p>
        </div>
        <a href="{{ route('ai-scheduler') }}" wire:navigate class="rounded-lg bg-[#0A3D91] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1565D8]">Buka AI Scheduler</a>
    </section>
</div>
