<x-layouts::app :title="__('Dashboard')">
    <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-6 sm:px-8 lg:px-10">
        <header class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91]">{{ __('Panel del empleado') }}</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ __('Mi asistencia') }}</h1>
                    <p class="mt-1 max-w-2xl text-sm text-slate-500">{{ __('Consulta tus registros recientes de entrada y salida.') }}</p>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-[#0f2f5f]/10 bg-[#0f2f5f]/5 px-4 py-2.5 shadow-xs">
                    <flux:avatar
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        class="bg-[#0f2f5f] text-white shadow-xs"
                    />
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[#1e4f91]">{{ __('Empleado') }}</span>
                        <span class="text-sm font-medium text-slate-900 leading-tight">{{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-950">{{ __('Últimos registros') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Historial ordenado desde el movimiento más reciente.') }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('Tipo') }}</th>
                            <th class="px-6 py-3">{{ __('Fecha') }}</th>
                            <th class="px-6 py-3">{{ __('Hora') }}</th>
                            <th class="px-6 py-3">{{ __('Ubicación') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($attendances as $attendance)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' => $attendance->type === 'entrada',
                                        'bg-amber-50 text-amber-700 ring-1 ring-amber-200' => $attendance->type === 'salida',
                                    ])>
                                        {{ str($attendance->type)->title() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-700">{{ $attendance->occurred_at->format('Y-m-d') }}</td>
                                <td class="px-6 py-4 text-slate-700">{{ $attendance->occurred_at->format('H:i:s') }}</td>
                                <td class="px-6 py-4 text-slate-700">
                                    @if ($attendance->latitude && $attendance->longitude)
                                        <a href="https://www.google.com/maps/search/?api=1&query={{ $attendance->latitude }},{{ $attendance->longitude }}" target="_blank" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 font-medium hover:underline">
                                            <svg class="size-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span>{{ round($attendance->latitude, 4) }}, {{ round($attendance->longitude, 4) }}</span>
                                        </a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                            <flux:icon.clock class="size-6" />
                                        </div>
                                        <p class="mt-3 font-medium text-slate-900">{{ __('Aún no hay registros de asistencia.') }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ __('Cuando escanees el QR público, tus movimientos aparecerán aquí.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</x-layouts::app>
