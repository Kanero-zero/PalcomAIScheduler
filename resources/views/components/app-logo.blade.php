@props([
    'sidebar' => false,
    'full' => false,
])

<a {{ $attributes->class(['min-w-0 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1565D8]', 'flex flex-col items-center gap-3' => $full, 'flex items-center gap-2.5' => ! $full]) }} aria-label="PALCOM AI Scheduler">
    @if ($full)
        <span class="block w-full max-w-xs rounded-xl bg-white p-3 ring-1 ring-zinc-200">
            <img src="{{ asset('palcomtech-logo.png') }}" alt="LKP PalComTech — Pendidikan Generasi AI" width="1718" height="314" class="h-auto w-full" />
        </span>
        <span class="text-sm font-semibold tracking-wide text-zinc-900 dark:text-white">PALCOM AI Scheduler</span>
    @else
        <x-app-logo-icon class="h-auto w-10 shrink-0 rounded-md bg-white p-1" />
        <span class="min-w-0 text-start leading-tight">
            <span class="block text-sm font-bold tracking-wide text-[#0A3D91] dark:text-blue-200">PALCOM</span>
            <span class="block text-xs font-medium text-zinc-600 dark:text-zinc-300">AI Scheduler</span>
        </span>
    @endif
</a>
