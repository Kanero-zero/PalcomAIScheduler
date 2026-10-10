<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" tooltip="Tutup navigasi" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('AI & Penjadwalan')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="sparkles" :href="route('ai-scheduler')" :current="request()->routeIs('ai-scheduler')" wire:navigate>
                        {{ __('AI Auto-Scheduler') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('activity-log')" :current="request()->routeIs('activity-log')" wire:navigate>
                        {{ __('Activity Log') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="check-circle" :href="route('approval-review')" :current="request()->routeIs('approval-review')" wire:navigate>
                        {{ __('Approval / Reject') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar" :href="route('schedules.index')" :current="request()->routeIs('schedules.*')" wire:navigate>
                        {{ __('Jadwal Mengajar') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Manajemen Data')" class="grid">
                    <flux:sidebar.item icon="user-group" :href="route('instructors.index')" :current="request()->routeIs('instructors.*')" wire:navigate>
                        {{ __('Instruktur') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="building-office-2" :href="route('rooms.index')" :current="request()->routeIs('rooms.*')" wire:navigate>
                        {{ __('Ruangan & Lab') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="academic-cap" :href="route('course-classes.index')" :current="request()->routeIs('course-classes.*')" wire:navigate>
                        {{ __('Kelas Kursus') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('instructor-leaves.index')" :current="request()->routeIs('instructor-leaves.*')" wire:navigate>
                        {{ __('Izin Instruktur') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <div class="px-3 py-2 text-xs text-zinc-400 dark:text-zinc-500">
                    LKP PalComTech &copy; {{ date('Y') }}
                </div>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" aria-label="Buka atau tutup navigasi" />

            <x-app-logo href="{{ route('dashboard') }}" class="ms-2" wire:navigate />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu class="max-w-[calc(100vw-2rem)]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid min-w-0 flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="whitespace-normal wrap-anywhere">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="whitespace-normal wrap-anywhere">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
