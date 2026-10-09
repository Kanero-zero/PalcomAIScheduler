<x-layouts::app :title="__('AI Auto-Scheduler')">
    <div class="flex flex-col gap-6 p-4">
        {{-- Header Halaman --}}
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ __('AI Auto-Scheduler') }}
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Rekomendasi otomatis instruktur pengganti dan penyesuaian ruangan ketika terjadi izin.') }}
                </p>
            </div>
            <div>
                {{-- Area tombol aksi (misal: Jalankan Analisis AI) --}}
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/50 dark:text-amber-400">
                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                    {{ __('Dalam Pengembangan') }}
                </span>
            </div>
        </div>

        {{-- Area Konten Utama / Skenario Rekomendasi (Dapat dikembangkan oleh tim UI) --}}
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/50">
            <div class="mx-auto flex max-w-md flex-col items-center justify-center gap-2">
                <div class="rounded-full bg-indigo-100 p-3 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400">
                    <flux:icon name="sparkles" class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ __('Rekomendasi AI Scheduler') }}
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Rekomendasi instruktur pengganti dan penyesuaian jadwal akan ditampilkan di sini setelah fitur AI tersedia. Saat ini belum ada analisis AI yang dijalankan.') }}
                </p>
            </div>
        </div>
    </div>
</x-layouts::app>
