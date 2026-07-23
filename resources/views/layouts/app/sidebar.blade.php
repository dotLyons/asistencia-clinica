<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen text-slate-900 dark:text-slate-100 antialiased">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-slate-200 bg-white/95 text-slate-800 shadow-xl shadow-slate-950/5 dark:border-slate-800 dark:bg-slate-900/95 dark:text-slate-100">
            <flux:sidebar.header class="border-b border-slate-200 dark:border-slate-800 px-4 py-5">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="px-3 py-4">
                <flux:sidebar.group :heading="__('Plataforma')" class="grid text-slate-500 dark:text-slate-400">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @if (auth()->user()->hasRole('administrador'))
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.sections')" :current="request()->routeIs('admin.sections')" wire:navigate>
                            {{ __('Secciones') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="border-t border-slate-200 dark:border-slate-800 p-3">
                <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
            </div>
        </flux:sidebar>

        <flux:header class="border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/90 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <div class="ms-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ config('app.name', 'Asistencia') }}</div>

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
