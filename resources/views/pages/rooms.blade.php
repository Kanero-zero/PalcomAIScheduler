<x-layouts::app :title="__('Ruangan & Lab')">
    <div class="flex flex-col gap-6 p-4">
        {{-- Header Halaman --}}
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ __('Ruangan & Lab') }}
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Kapasitas dan ketersediaan lab komputer / ruang kelas LKP PalComTech.') }}
                </p>
            </div>
        </div>

        {{-- Area Konten Utama / Tabel Ruangan --}}
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/50">
            <div class="mx-auto flex max-w-md flex-col items-center justify-center gap-2">
                <div class="rounded-full bg-amber-100 p-3 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
                    <flux:icon name="building-office-2" class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ __('Daftar Lab & Kapasitas') }}
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Partner tim dapat menambahkan kartu status lab (misal: Lab 1, Lab 2), kapasitas siswa, dan status ketersediaan di sini.') }}
                </p>
            </div>
        </div>
    </div>
</x-layouts::app>
