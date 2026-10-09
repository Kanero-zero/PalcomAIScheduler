<x-layouts::app :title="__('Pengajuan Izin Instruktur')">
    <div class="flex flex-col gap-6 p-4">
        {{-- Header Halaman --}}
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ __('Pengajuan Izin Instruktur') }}
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Pencatatan data izin instruktur yang menjadi pemicu (trigger) penjadwalan otomatis oleh AI.') }}
                </p>
            </div>
        </div>

        {{-- Area Konten Utama / Tabel Izin --}}
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/50">
            <div class="mx-auto flex max-w-md flex-col items-center justify-center gap-2">
                <div class="rounded-full bg-rose-100 p-3 text-rose-600 dark:bg-rose-950 dark:text-rose-400">
                    <flux:icon name="clock" class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ __('Form & Riwayat Izin Instruktur') }}
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Pengajuan dan riwayat izin instruktur akan ditampilkan pada halaman ini. Formulir pengajuan izin belum tersedia.') }}
                </p>
            </div>
        </div>
    </div>
</x-layouts::app>
