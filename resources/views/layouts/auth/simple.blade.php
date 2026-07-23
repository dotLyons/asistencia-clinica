<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="grid min-h-svh lg:grid-cols-[minmax(0,0.95fr)_minmax(420px,1fr)]">
            <section class="hidden bg-[#0f172a] text-white lg:flex lg:flex-col lg:justify-between lg:p-12 dark:border-e dark:border-slate-800">
                <div>
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3" wire:navigate>
                        <span class="flex size-11 items-center justify-center rounded-xl bg-white text-[#0f2f5f]">
                            <x-app-logo-icon class="size-6 fill-current" />
                        </span>
                        <span class="text-lg font-semibold tracking-tight">{{ config('app.name', 'Asistencia') }}</span>
                    </a>
                </div>

                <div class="max-w-md space-y-5">
                    <p class="text-sm font-medium uppercase tracking-[0.22em] text-blue-200">{{ __('Control corporativo') }}</p>
                    <h1 class="text-4xl font-semibold leading-tight tracking-tight">{{ __('Asistencia clara, segura y trazable.') }}</h1>
                    <p class="text-base leading-7 text-slate-300">{{ __('Un sistema enfocado en registrar ingresos y salidas con precisión, sin pasos innecesarios para el equipo.') }}</p>
                </div>

                <div class="grid grid-cols-3 gap-3 text-sm text-slate-300">
                    <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                        <span class="block text-2xl font-semibold text-white">QR</span>
                        <span>{{ __('Registro rápido') }}</span>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                        <span class="block text-2xl font-semibold text-white">24/7</span>
                        <span>{{ __('Disponible') }}</span>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                        <span class="block text-2xl font-semibold text-white">ID</span>
                        <span>{{ __('Trazabilidad') }}</span>
                    </div>
                </div>
            </section>

            <main class="flex min-h-svh items-center justify-center p-6 md:p-10 bg-slate-100 dark:bg-slate-950">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex justify-center lg:hidden">
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-3 font-semibold text-slate-900 dark:text-slate-100" wire:navigate>
                            <span class="flex size-10 items-center justify-center rounded-xl bg-[#0f2f5f] text-white">
                                <x-app-logo-icon class="size-6 fill-current" />
                            </span>
                            {{ config('app.name', 'Asistencia') }}
                        </a>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-950/8 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
