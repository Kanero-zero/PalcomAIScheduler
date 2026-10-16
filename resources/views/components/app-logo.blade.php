@props([
    'sidebar' => false,
    'full' => false,
])

<a {{ $attributes->class(['schedora-brand min-w-0 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0D67EF]', 'flex flex-col items-center gap-2 text-center' => $full, 'flex items-center gap-2.5 text-start' => ! $full]) }} aria-label="SCHEDORA AI">
    <x-app-logo-icon class="schedora-brand-icon h-12 w-12 shrink-0" />
    @if ($full)
        <span class="schedora-brand-wordmark text-base font-extrabold tracking-tight text-[#102444] dark:text-white">SCHEDORA <span class="text-[#0D67EF] dark:text-cyan-300">AI</span></span>
    @else
        <span class="schedora-brand-wordmark min-w-0 leading-tight">
            <span class="block whitespace-nowrap text-lg font-extrabold tracking-tight text-[#102444] dark:text-white">SCHEDORA <span class="text-[#0D67EF] dark:text-cyan-300">AI</span></span>
            <span class="block whitespace-nowrap text-[9px] font-semibold tracking-[0.14em] text-slate-500 dark:text-slate-400">ACADEMIC SCHEDULING</span>
        </span>
    @endif
</a>
