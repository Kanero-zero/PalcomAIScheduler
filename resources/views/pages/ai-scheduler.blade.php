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
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-400">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    {{ __('Tahap 1: Deterministik Aktif') }}
                </span>
            </div>
        </div>

        {{-- Komponen Livewire Scheduling Engine --}}
        <livewire:ai-scheduler />
    </div>
</x-layouts::app>
