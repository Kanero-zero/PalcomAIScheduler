<x-layouts::app :title="__('Kelas Kursus')">
    <div class="flex flex-col gap-6 p-4">
        {{-- Header Halaman --}}
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ __('Kelas Kursus') }}
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Daftar kelas kursus aktif, materi pelajaran, dan jumlah siswa terdaftar.') }}
                </p>
            </div>
        </div>

        {{-- Area Konten Utama / Tabel Kelas --}}
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/50">
            <div class="mx-auto flex max-w-md flex-col items-center justify-center gap-2">
                <div class="rounded-full bg-emerald-100 p-3 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                    <flux:icon name="academic-cap" class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ __('Daftar Kelas Kursus & Materi') }}
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Partner tim dapat menambahkan tabel batch kelas, mata pelajaran (Excel, Word, dll), dan jumlah murid di sini.') }}
                </p>
            </div>
        </div>
    </div>
</x-layouts::app>
