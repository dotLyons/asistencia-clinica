<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Inicio')])
    </head>
    <body class="min-h-screen bg-[radial-gradient(circle_at_top_left,#dce8f9_0,#f5f7fb_30rem,#eef2f7_100%)] text-slate-900">
        <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-5 py-6 sm:px-8 lg:px-10">
            <header class="flex items-center justify-between">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-[#0f2f5f] text-white shadow-sm">
                        <x-app-logo-icon class="size-6 fill-current" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight text-slate-950">{{ config('app.name', 'Asistencia') }}</span>
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-lg bg-[#0f2f5f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#173f7a]">
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                                {{ __('Log in') }}
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="rounded-lg bg-[#0f2f5f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#173f7a]">
                                    {{ __('Register') }}
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <section class="grid flex-1 items-center gap-10 py-16 lg:grid-cols-[1fr_460px]">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-[#1e4f91]">{{ __('Control corporativo de asistencia') }}</p>
                    <h1 class="mt-5 text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl">{{ __('Registros de entrada y salida sin fricción.') }}</h1>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-slate-600">{{ __('Un sistema sobrio para empleados: registro por QR, historial personal y trazabilidad básica para operar con orden desde el primer día.') }}</p>


                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl shadow-slate-950/10">
                    <div class="rounded-xl bg-[#0f172a] p-6 text-white">
                        <div class="flex items-center justify-between border-b border-white/10 pb-5">
                            <div>
                                <p class="text-sm text-slate-300">{{ __('Estado') }}</p>
                                <p class="mt-1 text-xl font-semibold">{{ __('Operativo') }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-semibold text-emerald-200">{{ __('Activo') }}</span>
                        </div>

                        <div class="mt-6 grid gap-4">
                            <div class="rounded-lg bg-white/8 p-4">
                                <p class="text-sm text-slate-300">{{ __('Método de registro') }}</p>
                                <p class="mt-1 font-semibold">{{ __('QR único para entrada y salida') }}</p>
                            </div>
                            <div class="rounded-lg bg-white/8 p-4">
                                <p class="text-sm text-slate-300">{{ __('Identificación') }}</p>
                                <p class="mt-1 font-semibold">{{ __('Sesión del empleado') }}</p>
                            </div>
                            <div class="rounded-lg bg-white/8 p-4">
                                <p class="text-sm text-slate-300">{{ __('Salida esperada') }}</p>
                                <p class="mt-1 font-semibold">{{ __('Historial claro en dashboard') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
