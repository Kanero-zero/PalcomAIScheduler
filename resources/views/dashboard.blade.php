<x-layouts::app :title="__('Dashboard')">
    <div class="flex flex-col gap-6 p-4">
        {{-- Welcome Banner --}}
        <div class="flex flex-col gap-2 rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 p-6 text-white shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ __('Selamat Datang di Palcom AI Scheduler') }}
                    </h1>
                    <p class="mt-1 text-sm text-blue-100">
                        {{ __('Sistem otomasi penjadwalan cerdas instruktur & lab LKP PalComTech berbasis Agentic AI.') }}
                    </p>
                </div>
                <div class="hidden sm:block">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1.5 text-xs font-semibold backdrop-blur-sm">
                        <flux:icon name="sparkles" class="size-4" />
                        AI Agent Standby
                    </span>
                </div>
            </div>
        </div>

        {{-- Stat Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Card 1 --}}
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex size-12 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400">
                    <flux:icon name="user-group" class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Instruktur') }}</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ \App\Models\Instructor::count() }}</div>
                </div>
            </div>

            {{-- Card 2 --}}
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex size-12 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                    <flux:icon name="building-office-2" class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Ruangan / Lab') }}</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ \App\Models\Room::count() }}</div>
                </div>
            </div>

            {{-- Card 3 --}}
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex size-12 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400">
                    <flux:icon name="academic-cap" class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Kelas Kursus') }}</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ \App\Models\CourseClass::count() }}</div>
                </div>
            </div>

            {{-- Card 4 --}}
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex size-12 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                    <flux:icon name="clock" class="size-6" />
                </div>
                <div>
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Izin Aktif') }}</div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ \App\Models\InstructorLeave::count() }}</div>
                </div>
            </div>
        </div>

        {{-- Area Desain untuk Tim --}}
        <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/50">
            <div class="mx-auto flex max-w-lg flex-col items-center justify-center gap-3">
                <div class="rounded-full bg-indigo-100 p-3 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400">
                    <flux:icon name="sparkles" class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ __('Area Kerja Tampilan (Untuk Tim UI)') }}
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                    {{ __('Semua menu sidebar, routing, dan model basis data sudah siap terhubung. Tim Anda dapat langsung merancang widget jadwal harian, log aktivitas AI, atau form simulasi di bagian ini.') }}
                </p>
                <div class="mt-2 flex flex-wrap gap-2 justify-center">
                    <flux:button :href="route('ai-scheduler')" icon="sparkles" variant="primary" wire:navigate>
                        {{ __('Buka AI Scheduler') }}
                    </flux:button>
                    <flux:button :href="route('schedules.index')" icon="calendar" wire:navigate>
                        {{ __('Lihat Jadwal') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
