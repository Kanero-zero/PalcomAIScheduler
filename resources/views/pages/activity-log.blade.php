<x-layouts::app :title="__('Activity Log')">
    @php
        // Seluruh data di halaman ini contoh/simulasi; bukan audit trail produksi.
        $activities = [
            [
                'id' => 1, 'at' => '09 Okt 2026, 08.45 WIB', 'type' => 'schedule', 'type_label' => 'Jadwal',
                'state' => 'success', 'state_label' => 'Berhasil', 'actor' => 'Sistem (simulasi)',
                'title' => 'Jadwal kelas diperbarui',
                'description' => 'Instruktur pengganti telah diterapkan pada kelas Microsoft Excel setelah persetujuan admin.',
                'subject' => 'Microsoft Excel - Lab 2',
            ],
            [
                'id' => 2, 'at' => '09 Okt 2026, 08.44 WIB', 'type' => 'approval', 'type_label' => 'Persetujuan',
                'state' => 'success', 'state_label' => 'Disetujui', 'actor' => 'Admin Demo',
                'title' => 'Rekomendasi instruktur disetujui',
                'description' => 'Admin memilih Wahyu sebagai instruktur pengganti untuk sesi yang terdampak.',
                'subject' => 'Pengajuan Izin #DEMO-001',
            ],
            [
                'id' => 3, 'at' => '09 Okt 2026, 08.42 WIB', 'type' => 'ai', 'type_label' => 'Analisis AI',
                'state' => 'success', 'state_label' => 'Selesai', 'actor' => 'Gemini AI (simulasi)',
                'title' => 'Pemeringkatan kandidat selesai',
                'description' => 'Analisis AI memberikan alasan tambahan terhadap kandidat yang telah lolos seleksi deterministik.',
                'subject' => 'Pengajuan Izin #DEMO-001',
            ],
            [
                'id' => 4, 'at' => '09 Okt 2026, 08.41 WIB', 'type' => 'engine', 'type_label' => 'Scheduling Engine',
                'state' => 'success', 'state_label' => 'Selesai', 'actor' => 'Sistem (simulasi)',
                'title' => 'Kandidat pengganti ditemukan',
                'description' => 'Pemeriksaan kompetensi, ketersediaan instruktur, dan konflik ruangan berhasil dijalankan.',
                'subject' => 'Pengajuan Izin #DEMO-001',
            ],
            [
                'id' => 5, 'at' => '09 Okt 2026, 08.40 WIB', 'type' => 'leave', 'type_label' => 'Pengajuan Izin',
                'state' => 'pending', 'state_label' => 'Menunggu', 'actor' => 'Admin Demo',
                'title' => 'Pengajuan izin instruktur dicatat',
                'description' => 'Admin memasukkan pengajuan izin untuk instruktur dan periode kelas terkait.',
                'subject' => 'Pengajuan Izin #DEMO-001',
            ],
            [
                'id' => 6, 'at' => '09 Okt 2026, 07.55 WIB', 'type' => 'approval', 'type_label' => 'Persetujuan',
                'state' => 'rejected', 'state_label' => 'Ditolak', 'actor' => 'Admin Demo',
                'title' => 'Usulan pengganti ditolak',
                'description' => 'Admin menolak usulan lain karena membutuhkan verifikasi ketersediaan tambahan.',
                'subject' => 'Pengajuan Izin #DEMO-002',
            ],
        ];

        $statusColors = [
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
            'pending' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
            'rejected' => 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300',
        ];
        $typeIcons = [
            'schedule' => 'calendar',
            'approval' => 'check-circle',
            'ai' => 'sparkles',
            'engine' => 'cog-6-tooth',
            'leave' => 'clock',
        ];
    @endphp

    <div
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 py-2 sm:p-4"
        x-data="{
            type: 'all', status: 'all', activities: @js($activities),
            get visibleCount() {
                return this.activities.filter(a =>
                    (this.type === 'all' || a.type === this.type) &&
                    (this.status === 'all' || a.state === this.status)
                ).length;
            }
        }"
    >
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">Activity Log</h1>
                <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-400">Rancangan riwayat pengajuan izin, analisis, persetujuan, dan pembaruan jadwal.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                <flux:icon name="information-circle" class="size-4" />
                Pratinjau - Data simulasi
            </span>
        </header>

        <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/20 dark:text-blue-200">
            <div class="flex items-start gap-3">
                <flux:icon name="shield-check" class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-semibold">Belum terhubung ke Activity Log backend</p>
                    <p class="mt-1 text-xs leading-5">Semua catatan berikut hanya contoh untuk meninjau desain dan filter. Tidak mewakili aktivitas nyata, tidak menyimpan data, dan tidak mengubah jadwal.</p>
                </div>
            </div>
        </div>

        <section aria-label="Filter riwayat aktivitas" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Riwayat Aktivitas</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"><span x-text="visibleCount">{{ count($activities) }}</span> dari {{ count($activities) }} aktivitas simulasi</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="space-y-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                        <span>Jenis Aktivitas</span>
                        <select x-model="type" aria-label="Filter jenis aktivitas" class="block w-full min-w-48 rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-800 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="all">Semua Aktivitas</option>
                            <option value="leave">Pengajuan Izin</option>
                            <option value="engine">Scheduling Engine</option>
                            <option value="ai">Analisis AI</option>
                            <option value="approval">Persetujuan</option>
                            <option value="schedule">Perubahan Jadwal</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                        <span>Status</span>
                        <select x-model="status" aria-label="Filter status" class="block w-full min-w-40 rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-800 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="all">Semua Status</option>
                            <option value="success">Berhasil / Selesai</option>
                            <option value="pending">Menunggu</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                    </label>
                </div>
            </div>
        </section>

        <section aria-label="Timeline aktivitas" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <ol class="space-y-0">
                @foreach ($activities as $activity)
                    <li
                        wire:key="demo-activity-{{ $activity['id'] }}"
                        x-show="(type === 'all' || type === '{{ $activity['type'] }}') && (status === 'all' || status === '{{ $activity['state'] }}')"
                        class="relative border-l-2 border-zinc-200 pb-6 pl-6 last:border-transparent last:pb-0 dark:border-zinc-700"
                    >
                        <span class="absolute -left-[13px] top-0 flex size-6 items-center justify-center rounded-full bg-blue-50 text-[#1565D8] ring-4 ring-white dark:bg-blue-950 dark:text-blue-300 dark:ring-zinc-900">
                            <flux:icon :name="$typeIcons[$activity['type']]" class="size-3.5" />
                        </span>
                        <article class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-800 dark:bg-zinc-800/30">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-medium text-[#1565D8] dark:text-blue-300">{{ $activity['type_label'] }}</span>
                                <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $statusColors[$activity['state']] }}">{{ $activity['state_label'] }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $activity['at'] }}</span>
                            </div>
                            <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">{{ $activity['title'] }}</h3>
                            <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $activity['description'] }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-zinc-200 pt-3 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                <span class="inline-flex items-center gap-1.5"><flux:icon name="user" class="size-3.5" />{{ $activity['actor'] }}</span>
                                <span class="inline-flex items-center gap-1.5"><flux:icon name="document-text" class="size-3.5" />{{ $activity['subject'] }}</span>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ol>
            <div x-show="visibleCount === 0" class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <flux:icon name="magnifying-glass" class="mx-auto size-6 text-zinc-400" />
                <p class="mt-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Tidak ada aktivitas pada kombinasi filter ini.</p>
                <button type="button" class="mt-3 text-sm font-semibold text-[#1565D8] hover:underline" @click="type = 'all'; status = 'all'">Reset filter</button>
            </div>
        </section>
    </div>
</x-layouts::app>