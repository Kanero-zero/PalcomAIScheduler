<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <link rel="stylesheet" href="{{ asset('schedora/sidebar-v2.css') }}">
        <script src="{{ asset('schedora/sidebar-v2.js') }}" defer></script>
    </head>
    <body class="schedora-app min-h-screen bg-[#F3F7FD] dark:bg-zinc-900">
        <flux:sidebar sticky collapsible="mobile" class="schedora-sidebar border-e border-[#E5ECF6] bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <div class="schedora-topbar">
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                    <button type="button" class="schedora-collapse" data-schedora-collapse aria-label="Ringkas sidebar" title="Ringkas sidebar" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m6-12-4 4 4 4"/></svg>
                    </button>
                    <flux:sidebar.collapse class="lg:hidden" tooltip="Tutup navigasi" />
                </div>
            </flux:sidebar.header>

            <nav class="schedora-navigation" aria-label="Navigasi SCHEDORA AI">
                <section class="schedora-section" aria-labelledby="schedora-section-workspace">
                    <h2 id="schedora-section-workspace" class="schedora-section-heading">RUANG KERJA</h2>
                    <a href="{{ route('dashboard') }}" class="schedora-nav-item {{ request()->routeIs('dashboard', 'dashboard.v2-preview') ? 'is-active' : '' }}" @if (request()->routeIs('dashboard', 'dashboard.v2-preview')) aria-current="page" @endif data-label="Dashboard" wire:navigate>
                        <flux:icon name="home" class="size-5 shrink-0"/><span class="schedora-item-label">Dashboard</span>
                    </a>
                    <a href="{{ route('ai-scheduler') }}" class="schedora-nav-item {{ request()->routeIs('ai-scheduler') ? 'is-active' : '' }}" @if (request()->routeIs('ai-scheduler')) aria-current="page" @endif data-label="AI Auto-Scheduler" wire:navigate>
                        <flux:icon name="sparkles" class="size-5 shrink-0"/><span class="schedora-item-label">AI Auto-Scheduler</span>
                    </a>
                    <a href="{{ route('approval-review') }}" class="schedora-nav-item {{ request()->routeIs('approval-review', 'approval-review.v2-preview') ? 'is-active' : '' }}" @if (request()->routeIs('approval-review', 'approval-review.v2-preview')) aria-current="page" @endif data-label="Approval / Reject" wire:navigate>
                        <flux:icon name="check-circle" class="size-5 shrink-0"/><span class="schedora-item-label">Approval / Reject</span>
                    </a>
                    @if (auth()->user()?->isBaak())
                        <a href="{{ route('class-proposal-approvals.index') }}" class="schedora-nav-item {{ request()->routeIs('class-proposal-approvals.*') ? 'is-active' : '' }}" @if (request()->routeIs('class-proposal-approvals.*')) aria-current="page" @endif data-label="Persetujuan Kelas Baru">
                            <flux:icon name="clipboard-document-check" class="size-5 shrink-0"/><span class="schedora-item-label">Persetujuan Kelas Baru</span>
                        </a>
                    @endif
                    <a href="{{ route('activity-log') }}" class="schedora-nav-item {{ request()->routeIs('activity-log') ? 'is-active' : '' }}" @if (request()->routeIs('activity-log')) aria-current="page" @endif data-label="Activity Log" wire:navigate>
                        <flux:icon name="clock" class="size-5 shrink-0"/><span class="schedora-item-label">Activity Log</span>
                    </a>
                </section>

                <section class="schedora-section" aria-labelledby="schedora-section-data">
                    <h2 id="schedora-section-data" class="schedora-section-heading">MANAJEMEN AKADEMIK</h2>
                    <a href="{{ route('instructors.index') }}" class="schedora-nav-item {{ request()->routeIs('instructors.*') ? 'is-active' : '' }}" @if (request()->routeIs('instructors.*')) aria-current="page" @endif data-label="Instruktur" wire:navigate>
                        <flux:icon name="user-group" class="size-5 shrink-0"/><span class="schedora-item-label">Instruktur</span>
                    </a>
                    <a href="{{ route('rooms.index') }}" class="schedora-nav-item {{ request()->routeIs('rooms.*') ? 'is-active' : '' }}" @if (request()->routeIs('rooms.*')) aria-current="page" @endif data-label="Ruangan & Lab" wire:navigate>
                        <flux:icon name="building-office-2" class="size-5 shrink-0"/><span class="schedora-item-label">Ruangan &amp; Lab</span>
                    </a>
                    <a href="{{ route('course-classes.index') }}" class="schedora-nav-item {{ request()->routeIs('course-classes.*') ? 'is-active' : '' }}" @if (request()->routeIs('course-classes.*')) aria-current="page" @endif data-label="Kelas Kursus" wire:navigate>
                        <flux:icon name="academic-cap" class="size-5 shrink-0"/><span class="schedora-item-label">Kelas Kursus</span>
                    </a>
                    <a href="{{ route('instructor-leaves.index') }}" class="schedora-nav-item {{ request()->routeIs('instructor-leaves.*') ? 'is-active' : '' }}" @if (request()->routeIs('instructor-leaves.*')) aria-current="page" @endif data-label="Izin Instruktur" wire:navigate>
                        <flux:icon name="calendar" class="size-5 shrink-0"/><span class="schedora-item-label">Izin Instruktur</span>
                    </a>
                </section>

                <section class="schedora-section schedora-section-future" aria-labelledby="schedora-section-future">
                    <h2 id="schedora-section-future" class="schedora-section-heading">PENGEMBANGAN SELANJUTNYA</h2>
                    <button class="schedora-nav-item is-disabled" type="button" disabled title="Untuk pengembangan selanjutnya" aria-label="Pengajuan Saya — Untuk pengembangan selanjutnya" data-label="Pengajuan Saya">
                        <flux:icon name="inbox" class="size-5 shrink-0"/><span class="schedora-item-label">Pengajuan Saya</span><flux:icon name="lock-closed" class="schedora-lock size-3 shrink-0" />
                    </button>
                    <button class="schedora-nav-item is-disabled" type="button" disabled title="Untuk pengembangan selanjutnya" aria-label="Jadwal Mengajar — Untuk pengembangan selanjutnya" data-label="Jadwal Mengajar">
                        <flux:icon name="calendar" class="size-5 shrink-0"/><span class="schedora-item-label">Jadwal Mengajar</span><flux:icon name="lock-closed" class="schedora-lock size-3 shrink-0" />
                    </button>
                    <button class="schedora-nav-item is-disabled" type="button" disabled title="Untuk pengembangan selanjutnya" aria-label="Notifikasi — Untuk pengembangan selanjutnya" data-label="Notifikasi">
                        <flux:icon name="bell" class="size-5 shrink-0"/><span class="schedora-item-label">Notifikasi</span><flux:icon name="lock-closed" class="schedora-lock size-3 shrink-0" />
                    </button>
                    <p class="schedora-future-notice">Untuk pengembangan selanjutnya</p>
                </section>
            </nav>

            <flux:spacer />

            <div class="schedora-sidebar-bottom">
                <div class="schedora-current-role" aria-label="Peran akun aktif">{{ strtoupper((string) (auth()->user()->role ?? 'pengguna')) }}</div>
                <div class="schedora-copyright">&copy; {{ date('Y') }} SCHEDORA AI</div>
                <x-desktop-user-menu class="hidden lg:block" />
            </div>
        </flux:sidebar>

        <!-- Mobile navigation remains managed by existing Flux drawer. -->
        <flux:header class="schedora-mobile-header lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" aria-label="Buka atau tutup navigasi" />
            <x-app-logo href="{{ route('dashboard') }}" class="ms-2" wire:navigate />
            <flux:spacer />
            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                <flux:menu class="max-w-[calc(100vw-2rem)]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                                <div class="grid min-w-0 flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="whitespace-normal wrap-anywhere">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="whitespace-normal wrap-anywhere">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">{{ __('Log out') }}</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
