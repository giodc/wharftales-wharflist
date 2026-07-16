<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <flux:sidebar.brand name="WharfList" href="{{ route('dashboard') }}" wire:navigate>
                    <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                        <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
                    </x-slot>
                </flux:sidebar.brand>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('WharfList')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="queue-list" :href="route('lists.index')" :current="request()->routeIs('lists.*')" wire:navigate>
                        {{ __('Lists') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="globe-alt" :href="route('sites.index')" :current="request()->routeIs('sites.*')" wire:navigate>
                        {{ __('Sites') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('subscribers.index')" :current="request()->routeIs('subscribers.*')" wire:navigate>
                        {{ __('Subscribers') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-up-tray" :href="route('import.wizard')" :current="request()->routeIs('import.*')" wire:navigate>
                        {{ __('Import') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="envelope" :href="route('campaigns.index')" :current="request()->routeIs('campaigns.index') || request()->routeIs('campaigns.status')" wire:navigate>
                        {{ __('Campaigns') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="pencil-square" :href="route('campaigns.compose')" :current="request()->routeIs('campaigns.compose')" wire:navigate>
                        {{ __('Compose') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Settings')" class="grid">
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('settings.general')" :current="request()->routeIs('settings.general')" wire:navigate>
                        {{ __('General') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="envelope-open" :href="route('settings.smtp')" :current="request()->routeIs('settings.smtp')" wire:navigate>
                        {{ __('Mail') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="shield-check" :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate>
                        {{ __('Security') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-group" :href="route('settings.users')" :current="request()->routeIs('settings.users')" wire:navigate>
                        {{ __('Users') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('settings.scheduler')" :current="request()->routeIs('settings.scheduler')" wire:navigate>
                        {{ __('Scheduler') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.general')" icon="cog" wire:navigate>
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
