<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Registrando Asistencia')])
    </head>
    <body class="min-h-screen bg-[radial-gradient(circle_at_top_left,#dce8f9_0,#f5f7fb_30rem,#eef2f7_100%)] text-slate-900 flex items-center justify-center p-6 antialiased">
        <main class="w-full max-w-md">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-2xl shadow-slate-950/10 text-center">
                <!-- Logo -->
                <div class="inline-flex items-center gap-3 font-semibold text-slate-950 mb-8">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-[#0f2f5f] text-white shadow-sm">
                        <x-app-logo-icon class="size-6 fill-current" />
                    </span>
                    <span class="text-lg tracking-tight">{{ config('app.name', 'Asistencia') }}</span>
                </div>

                @if ($error)
                    <!-- Section Error State -->
                    <div class="space-y-6">
                        <div class="flex justify-center">
                            <div class="flex size-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-4 ring-amber-100">
                                <flux:icon.exclamation-triangle class="size-6" />
                            </div>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">{{ __('Acceso Denegado') }}</h2>
                            <p class="text-sm text-slate-600 mt-2 leading-relaxed">
                                {{ $error }}
                            </p>
                        </div>
                        <div class="pt-2">
                            <a href="{{ route('dashboard') }}" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-100 border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer transition-colors">
                                {{ __('Volver al Dashboard') }}
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Loading State -->
                    <div id="loading-state" class="space-y-6">
                        <div class="flex justify-center">
                            <div class="animate-spin rounded-full h-12 w-12 border-4 border-slate-200 border-t-[#0f2f5f]"></div>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">{{ __('Obteniendo ubicación...') }}</h2>
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#1e4f91] mt-1">{{ __('Sección: :name', ['name' => $section->name]) }}</p>
                            <p class="text-sm text-slate-500 mt-2 leading-relaxed">
                                {{ __('Por favor, permite el acceso a tu ubicación cuando el navegador lo solicite para registrar tu asistencia.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Error State (Hidden by default) -->
                    <div id="error-state" class="hidden space-y-6">
                        <div class="flex justify-center">
                            <div class="flex size-12 items-center justify-center rounded-full bg-rose-50 text-rose-600 ring-4 ring-rose-100">
                                <flux:icon.exclamation-triangle class="size-6" />
                            </div>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">{{ __('Error de ubicación') }}</h2>
                            <p id="error-message" class="text-sm text-slate-600 mt-2 leading-relaxed"></p>
                        </div>
                        <div class="pt-2">
                            <button onclick="window.location.reload();" class="inline-flex w-full items-center justify-center rounded-lg bg-[#0f2f5f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#173f7a] cursor-pointer transition-colors">
                                {{ __('Intentar nuevamente') }}
                            </button>
                        </div>
                    </div>

                    <!-- Hidden Form -->
                    <form id="attendance-form" action="{{ route('attendance.scan.store', $section->uuid) }}" method="POST">
                        @csrf
                        <input type="hidden" name="latitude" id="latitude">
                        <input type="hidden" name="longitude" id="longitude">
                    </form>
                @endif
            </div>
        </main>

        @if (! $error)
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (!navigator.geolocation) {
                        showError("{{ __('Tu navegador o dispositivo no soporta la geolocalización.') }}");
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            // Populate values and submit
                            document.getElementById('latitude').value = position.coords.latitude;
                            document.getElementById('longitude').value = position.coords.longitude;
                            document.getElementById('attendance-form').submit();
                        },
                        function(error) {
                            let msg = "{{ __('No se pudo obtener tu ubicación. Por favor, asegúrate de habilitar los permisos en tu navegador.') }}";
                            if (error.code === error.PERMISSION_DENIED) {
                                msg = "{{ __('Has denegado el acceso a la ubicación. Es obligatorio permitir la ubicación para registrar tu asistencia.') }}";
                            } else if (error.code === error.POSITION_UNAVAILABLE) {
                                msg = "{{ __('La información de ubicación no está disponible en este momento.') }}";
                            } else if (error.code === error.TIMEOUT) {
                                msg = "{{ __('Se agotó el tiempo de espera al intentar obtener tu ubicación.') }}";
                            }
                            showError(msg);
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );

                    function showError(message) {
                        document.getElementById('loading-state').classList.add('hidden');
                        document.getElementById('error-state').classList.remove('hidden');
                        document.getElementById('error-message').textContent = message;
                    }
                });
            </script>
        @endif
    </body>
</html>
