<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('QR de asistencia')])
    </head>
    <body class="min-h-screen bg-[radial-gradient(circle_at_top_left,#dce8f9_0,#f5f7fb_30rem,#eef2f7_100%)] text-slate-900">
        <main class="mx-auto flex min-h-screen w-full max-w-4xl flex-col justify-center gap-8 px-5 py-10">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
                <div class="grid lg:grid-cols-[1fr_380px]">
                    <div class="flex flex-col justify-between bg-[#0f172a] p-8 text-white lg:p-10">
                        <div>
                            <div class="inline-flex items-center gap-3">
                                <span class="flex size-11 items-center justify-center rounded-xl bg-white text-[#0f2f5f]">
                                    <x-app-logo-icon class="size-6 fill-current" />
                                </span>
                                <span class="font-semibold">{{ config('app.name', 'Asistencia') }}</span>
                            </div>

                            <div class="mt-14 max-w-md">
                                <p class="text-sm font-medium uppercase tracking-[0.22em] text-blue-200">{{ __('Registro de asistencia') }}</p>
                                <h1 class="mt-3 text-3xl font-semibold tracking-tight">{{ __('QR público para empleados') }}</h1>
                                <p class="mt-4 text-sm leading-6 text-slate-300">{{ __('Imprime este código y colócalo en el punto de entrada. El empleado iniciará sesión si hace falta y el sistema registrará su movimiento automáticamente.') }}</p>
                            </div>
                        </div>

                        <div class="mt-10 border-t border-white/10 pt-5 text-sm text-slate-300">
                            {{ __('El mismo QR sirve para entrada y salida.') }}
                        </div>
                    </div>

                    <div class="p-8 lg:p-10">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                            <div class="rounded-lg border border-slate-200 bg-white p-4">
                                <img
                                    src="{{ route('attendance.qr.image') }}"
                                    alt="{{ __('Código QR de asistencia') }}"
                                    class="mx-auto aspect-square w-full max-w-72"
                                >
                            </div>
                        </div>

                        <div class="mt-6 grid gap-3">
                            <a
                                href="{{ route('attendance.qr.download') }}"
                                class="inline-flex items-center justify-center rounded-lg bg-[#0f2f5f] px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-[#173f7a]"
                            >
                                {{ __('Descargar QR') }}
                            </a>
                            <a
                                href="{{ route('attendance.qr.image') }}"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                {{ __('Abrir imagen SVG') }}
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
