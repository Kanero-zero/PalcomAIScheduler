<x-layouts::app :title="__('Dashboard Admin')">
    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-8 py-2 sm:p-4">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-semibold tracking-widest text-[#1565D8] uppercase dark:text-blue-300">PalComTech / AI Scheduler</p>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">{{ __('Dashboard Admin') }}</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Satu tempat untuk memantau operasional pembelajaran.') }}</p>
            </div>
            <flux:button :href="route('schedules.index')" icon="calendar-days" wire:navigate>
                {{ __('Lihat Jadwal') }}
            </flux:button>
        </header>

        <section aria-labelledby="welcome-heading" class="relative isolate overflow-hidden rounded-2xl bg-[#0A3D91] p-6 text-white sm:p-8">
            <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-12 -z-10 size-80 rounded-full border-[48px] border-white/5"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -right-6 -bottom-36 -z-10 size-80 rounded-full bg-[#1565D8]/40"></div>
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl space-y-3">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold tracking-widest text-[#FFC928] uppercase">
                        <span aria-hidden="true" class="h-px w-6 bg-[#FFC928]"></span>
                        {{ __('Ruang kerja admin') }}
                    </span>
                    <h2 id="welcome-heading" class="text-2xl leading-tight font-semibold tracking-tight sm:text-3xl">{{ __('Kelola hari ini. Siapkan kelas berikutnya.') }}</h2>
                    <p class="max-w-xl text-sm leading-6 text-blue-100">{{ __('Pantau data instruktur, kelas, dan ruangan. Akses pengajuan izin serta penjadwalan dari satu dashboard.') }}</p>
                </div>
                <a href="{{ route('ai-scheduler') }}" wire:navigate class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#FFC928] px-5 py-3 text-sm font-semibold text-[#0A3D91] transition-colors hover:bg-yellow-300 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                    <flux:icon name="sparkles" class="size-5" />
                    {{ __('Buka AI Scheduler') }}
                    <flux:icon name="arrow-up-right" class="size-4" />
                </a>
            </div>
        </section>

        <section aria-labelledby="overview-heading" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="overview-heading" class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Ringkasan operasional') }}</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total data terdaftar di sistem') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('instructors.index') }}" wire:navigate class="group flex flex-col gap-5 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs transition-colors hover:border-[#1565D8] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-blue-400">
                    <div class="flex items-center justify-between">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-[#1565D8] dark:bg-blue-950 dark:text-blue-300"><flux:icon name="user-group" class="size-5" /></span>
                        <flux:icon name="arrow-up-right" class="size-4 text-zinc-400 group-hover:text-[#1565D8] dark:group-hover:text-blue-300" />
                    </div>
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Instruktur') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-zinc-900 tabular-nums dark:text-white">{{ \App\Models\Instructor::count() }}</p>
                    </div>
                    <p class="border-t border-zinc-100 pt-3 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">{{ __('Lihat data instruktur') }}</p>
                </a>
                <a href="{{ route('course-classes.index') }}" wire:navigate class="group flex flex-col gap-5 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs transition-colors hover:border-[#1565D8] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-blue-400">
                    <div class="flex items-center justify-between">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-[#1565D8] dark:bg-blue-950 dark:text-blue-300"><flux:icon name="academic-cap" class="size-5" /></span>
                        <flux:icon name="arrow-up-right" class="size-4 text-zinc-400 group-hover:text-[#1565D8] dark:group-hover:text-blue-300" />
                    </div>
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Kelas Kursus') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-zinc-900 tabular-nums dark:text-white">{{ \App\Models\CourseClass::count() }}</p>
                    </div>
                    <p class="border-t border-zinc-100 pt-3 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">{{ __('Lihat data kelas') }}</p>
                </a>
                <a href="{{ route('rooms.index') }}" wire:navigate class="group flex flex-col gap-5 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs transition-colors hover:border-[#1565D8] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-blue-400">
                    <div class="flex items-center justify-between">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-[#1565D8] dark:bg-blue-950 dark:text-blue-300"><flux:icon name="building-office-2" class="size-5" /></span>
                        <flux:icon name="arrow-up-right" class="size-4 text-zinc-400 group-hover:text-[#1565D8] dark:group-hover:text-blue-300" />
                    </div>
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Ruangan / Lab') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-zinc-900 tabular-nums dark:text-white">{{ \App\Models\Room::count() }}</p>
                    </div>
                    <p class="border-t border-zinc-100 pt-3 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">{{ __('Lihat data ruangan') }}</p>
                </a>
                <a href="{{ route('instructor-leaves.index') }}" wire:navigate class="group flex flex-col gap-5 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs transition-colors hover:border-[#1565D8] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-blue-400">
                    <div class="flex items-center justify-between">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-[#FFC928]/20 text-[#0A3D91] dark:text-[#FFC928]"><flux:icon name="clock" class="size-5" /></span>
                        <flux:icon name="arrow-up-right" class="size-4 text-zinc-400 group-hover:text-[#1565D8] dark:group-hover:text-blue-300" />
                    </div>
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Pengajuan Izin') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-zinc-900 tabular-nums dark:text-white">{{ \App\Models\InstructorLeave::count() }}</p>
                    </div>
                    <p class="border-t border-zinc-100 pt-3 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">{{ __('Seluruh status pengajuan') }}</p>
                </a>
            </div>
        </section>

        <livewire:dashboard-monitor />
        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">Ringkasan menggunakan data yang tersimpan saat halaman dimuat. Kalender dan agenda hanya menampilkan jadwal yang tercatat di sistem.</p>
    </div>
</x-layouts::app>
