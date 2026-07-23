<main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-6 sm:px-8 lg:px-10">
    <header class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91] dark:text-blue-400">{{ __('Administración') }}</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ __('Gestión de Secciones') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('Crea y administra las secciones del establecimiento. Cada sección tiene su propio código QR de asistencia en formato JPG.') }}</p>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[380px_1fr]">
        <!-- Create Section Form -->
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm h-fit dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100 mb-4">{{ __('Crear Nueva Sección') }}</h2>
            <form wire:submit="createSection" class="space-y-4">
                <flux:input 
                    wire:model="name" 
                    :label="__('Nombre de la Sección')" 
                    type="text" 
                    placeholder="{{ __('Ej. Recepción, Quirófano...') }}" 
                    required 
                />

                <flux:button variant="primary" type="submit" class="w-full cursor-pointer">
                    {{ __('Crear Sección') }}
                </flux:button>
            </form>
        </section>

        <!-- Sections List -->
        <section class="flex flex-col gap-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white/95 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                <div class="relative flex-1 max-w-md">
                    <flux:input 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="{{ __('Buscar secciones...') }}" 
                        icon="magnifying-glass"
                    />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-6 py-3">{{ __('Nombre') }}</th>
                                <th class="px-6 py-3">{{ __('ID de Escaneo (UUID)') }}</th>
                                <th class="px-6 py-3">{{ __('Total Registros') }}</th>
                                <th class="px-6 py-3 text-right">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($sections as $section)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100">
                                        {{ $section->name }}
                                    </td>
                                    <td class="px-6 py-4 text-xs font-mono text-slate-500 dark:text-slate-400">
                                        {{ $section->uuid }}
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300 font-semibold">
                                        {{ $section->attendances_count }}
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-1 whitespace-nowrap">
                                        <flux:button 
                                            size="sm" 
                                            variant="filled" 
                                            icon="arrow-down-tray" 
                                            wire:click="downloadQr({{ $section->id }})"
                                            class="cursor-pointer bg-[#0f2f5f] text-white hover:bg-[#173f7a] dark:bg-blue-600 dark:hover:bg-blue-500"
                                            title="{{ __('Descargar QR (JPG)') }}"
                                            square
                                        />
                                        <flux:button 
                                            size="sm" 
                                            variant="danger" 
                                            icon="trash" 
                                            wire:click="deleteSection({{ $section->id }})"
                                            wire:confirm="{{ __('¿Estás seguro de que deseas eliminar esta sección? Se desvinculará de sus registros de asistencia.') }}"
                                            class="cursor-pointer"
                                            title="{{ __('Eliminar') }}"
                                            square
                                        />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                        {{ __('No se encontraron secciones.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($sections && $sections->hasPages())
                    <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-4">
                        {{ $sections->links() }}
                    </div>
                @endif
            </div>
        </section>
    </div>
</main>
