<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('QR de asistencia')])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
        <main class="mx-auto flex min-h-screen w-full max-w-xl flex-col items-center justify-center gap-6 p-6">
            <div class="w-full rounded-lg border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-5 text-center">
                    <h1 class="text-xl font-semibold">{{ __('QR de asistencia') }}</h1>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Imprime este código para que los empleados registren su asistencia.') }}
                    </p>
                </div>

                <div class="mx-auto flex aspect-square w-full max-w-80 items-center justify-center rounded-lg border border-zinc-200 bg-white p-4">
                    <img
                        src="{{ route('attendance.qr.image') }}"
                        alt="{{ __('Código QR de asistencia') }}"
                        class="size-full"
                    >
                </div>

                <div class="mt-6 flex justify-center">
                    <a
                        href="{{ route('attendance.qr.download') }}"
                        class="inline-flex items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                    >
                        {{ __('Descargar QR') }}
                    </a>
                </div>
            </div>
        </main>
    </body>
</html>
