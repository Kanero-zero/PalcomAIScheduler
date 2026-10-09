<x-layouts::app :title="__('Pengajuan Izin Instruktur')">
    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 py-2 sm:p-4">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="text-xs font-semibold tracking-widest text-[#1565D8] uppercase dark:text-blue-300">{{ __('Manajemen instruktur') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">{{ __('Pengajuan Izin Instruktur') }}</h1>
                <p class="max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Siapkan informasi ketidakhadiran agar penyesuaian jadwal kelas dapat direncanakan dengan baik.') }}</p>
            </div>
            <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-800">
                <span aria-hidden="true" class="size-1.5 rounded-full bg-[#FFC928]"></span>
                {{ __('Formulir aktif') }}
            </span>
        </header>

        <div role="status" class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900 dark:bg-amber-950/20">
            <flux:icon name="information-circle" class="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
            <div class="min-w-0 space-y-1">
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('Pengajuan izin tersedia') }}</p>
                <p id="leave-connection-help" class="text-sm leading-6 text-amber-900/80 dark:text-amber-100/80">{{ __('Pilih instruktur, tanggal, dan jam izin. Pengajuan akan dicatat berstatus pending dan rekomendasi jadwal akan ditampilkan. Jadwal mengajar tidak diubah otomatis.') }}</p>
            </div>
        </div>

        <div class="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-3">
            <section aria-labelledby="leave-form-heading" class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs xl:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start gap-3 border-b border-zinc-100 p-5 sm:p-6 dark:border-zinc-800">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">
                        <flux:icon name="calendar-days" class="size-5" />
                    </span>
                    <div class="space-y-1">
                        <h2 id="leave-form-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Detail pengajuan izin') }}</h2>
                        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Field bertanda * wajib diisi.') }}</p>
                    </div>
                </div>


                <livewire:instructor-leave-form />
            </section>

            <aside aria-labelledby="leave-checks-heading" class="flex min-w-0 flex-col gap-5">
                <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white dark:border-blue-900 dark:bg-zinc-900">
                    <div class="space-y-3 bg-[#0A3D91] p-5 text-white sm:p-6">
                        <p class="text-xs font-semibold tracking-widest text-[#FFC928] uppercase">{{ __('Alur evaluasi izin') }}</p>
                        <h2 id="leave-checks-heading" class="text-lg font-semibold">{{ __('Dari izin ke rencana pengganti') }}</h2>
                        <p class="text-sm leading-6 text-blue-100">{{ __('Data pengajuan nantinya digunakan untuk pemeriksaan berikut oleh mesin penjadwalan.') }}</p>
                    </div>
                    <ol class="space-y-5 p-5 sm:p-6">
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">01</span>
                            <div><h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Jadwal terdampak') }}</h3><p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Kelas yang beririsan dengan tanggal dan rentang waktu izin.') }}</p></div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">02</span>
                            <div><h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Kesesuaian kompetensi') }}</h3><p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Keahlian calon instruktur pengganti sesuai materi kelas.') }}</p></div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">03</span>
                            <div><h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Bentrok instruktur') }}</h3><p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Jadwal mengajar dan izin lain dari calon pengganti.') }}</p></div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">04</span>
                            <div><h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Ketersediaan ruangan') }}</h3><p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Kapasitas, status, dan bentrok penggunaan ruangan.') }}</p></div>
                        </li>
                    </ol>
                </section>
                <section aria-labelledby="leave-history-heading" class="rounded-2xl border border-dashed border-zinc-300 p-5 dark:border-zinc-700">
                    <h2 id="leave-history-heading" class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Riwayat pengajuan') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Riwayat izin belum ditampilkan pada halaman ini. Pengajuan baru disimpan dengan status pending dan harus ditinjau admin.') }}</p>
                </section>
            </aside>
        </div>
    </div>
</x-layouts::app>
