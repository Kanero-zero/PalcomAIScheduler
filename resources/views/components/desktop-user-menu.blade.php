<flux:dropdown position="bottom" align="start" {{ $attributes->class('min-w-0') }}>
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
        class="[&>span]:min-w-0 [&>span]:flex-1 [&>span]:overflow-visible [&>span]:text-left [&>span]:text-clip [&>span]:whitespace-normal [&>span]:wrap-anywhere [&>span]:leading-5"
    />

    <flux:menu class="w-64 max-w-[calc(100vw-2rem)]">
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
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
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
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
