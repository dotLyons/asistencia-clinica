<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\BillingPeriod;
use App\Models\User;

new class extends Component
{
    use WithPagination;

    // Form fields
    public string $start_date = '';
    public string $end_date = '';
    public string $max_amount = '';
    public array $selectedPrestadores = []; // IDs of selected providers

    protected $listeners = ['refreshComponent' => '$refresh'];

    /**
     * Mount the component and check permissions.
     */
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('prestador'), 403);
    }

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'max_amount' => 'required|numeric|min:0.01',
            'selectedPrestadores' => 'required|array|min:1',
            'selectedPrestadores.*' => 'exists:users,id',
        ];
    }

    /**
     * Custom validation attributes.
     */
    protected function validationAttributes(): array
    {
        return [
            'start_date' => __('fecha de inicio'),
            'end_date' => __('fecha de fin'),
            'max_amount' => __('monto máximo'),
            'selectedPrestadores' => __('prestadores'),
        ];
    }

    /**
     * Save the new billing period.
     */
    public function save(): void
    {
        $this->validate();

        // Create Billing Period
        $period = BillingPeriod::create([
            'creator_id' => auth()->id(),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'max_amount' => $this->max_amount,
            'status' => 'pending_approval',
        ]);

        // Link selected providers + the creator themselves
        $allUsers = array_unique(array_merge($this->selectedPrestadores, [auth()->id()]));
        $period->users()->sync($allUsers);

        // Reset
        $this->reset(['start_date', 'end_date', 'max_amount', 'selectedPrestadores']);

        // Toast
        \Flux\Flux::toast(variant: 'success', text: __('Ronda de facturación creada y pendiente de aprobación por el director.'));

        // Close modal
        $this->dispatch('close-modal', 'create-period-modal');
    }

    /**
     * Request cancellation (baja) of the billing period.
     */
    public function requestCancellation(int $id): void
    {
        $period = BillingPeriod::findOrFail($id);

        // Verify that the user is the creator (prestador jefe)
        abort_unless($period->creator_id === auth()->id(), 403);

        if ($period->status === 'approved') {
            $period->status = 'pending_cancellation';
            $period->save();
            \Flux\Flux::toast(variant: 'success', text: __('Solicitud de baja enviada al director.'));
        } elseif ($period->status === 'pending_approval') {
            // If it was pending approval, we can just delete it/cancel it directly
            $period->delete();
            \Flux\Flux::toast(variant: 'success', text: __('Ronda de facturación eliminada correctamente.'));
        }
    }

    /**
     * Provide data to the view.
     */
    public function with(): array
    {
        // Get all providers (roles: prestador) except current user for selection
        $otherPrestadores = User::role('prestador')
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get();

        // Get billing periods where current user is linked OR creator
        $periods = BillingPeriod::where('creator_id', auth()->id())
            ->orWhereHas('users', function ($q) {
                $q->where('users.id', auth()->id());
            })
            ->with(['creator', 'users'])
            ->latest()
            ->paginate(10);

        return [
            'otherPrestadores' => $otherPrestadores,
            'periods' => $periods,
        ];
    }
};
?>

<main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-6 sm:px-8 lg:px-10">
    <!-- Header -->
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91] dark:text-blue-400">{{ __('Facturación') }}</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ __('Rondas de Facturación') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                {{ __('Crea y administra los periodos de facturación. Si eres el Prestador Jefe de la ronda, podrás administrar la baja.') }}
            </p>
        </div>

        <flux:modal.trigger name="create-period-modal">
            <flux:button variant="primary" icon="plus" class="cursor-pointer bg-[#0f2f5f] hover:bg-[#173f7a] text-white dark:bg-blue-600 dark:hover:bg-blue-500">
                {{ __('Nueva Ronda') }}
            </flux:button>
        </flux:modal.trigger>
    </header>

    <!-- Table of rounds -->
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">{{ __('Rondas y Periodos Activos') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Listado de periodos a los que estás vinculado.') }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th class="px-6 py-3">{{ __('Ronda ID') }}</th>
                        <th class="px-6 py-3">{{ __('Fecha Inicio') }}</th>
                        <th class="px-6 py-3">{{ __('Fecha Fin') }}</th>
                        <th class="px-6 py-3">{{ __('Monto Máximo') }}</th>
                        <th class="px-6 py-3">{{ __('Prestador Jefe (Creador)') }}</th>
                        <th class="px-6 py-3">{{ __('Integrantes') }}</th>
                        <th class="px-6 py-3">{{ __('Estado') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($periods as $period)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                            <!-- ID -->
                            <td class="px-6 py-4 font-semibold text-slate-950 dark:text-slate-100">
                                #{{ $period->id }}
                            </td>
                            <!-- Inicio -->
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                {{ $period->start_date->format('d/m/Y') }}
                            </td>
                            <!-- Fin -->
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                {{ $period->end_date->format('d/m/Y') }}
                            </td>
                            <!-- Monto Maximo -->
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-slate-100">
                                ${{ number_format($period->max_amount, 2, ',', '.') }}
                            </td>
                            <!-- Creador -->
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                {{ $period->creator->name }}
                                @if ($period->creator_id === auth()->id())
                                    <span class="ml-1 inline-flex items-center rounded-md bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 ring-1 ring-blue-600/10 dark:bg-blue-950/60 dark:text-blue-400 dark:ring-blue-800">
                                        {{ __('Tú') }}
                                    </span>
                                @endif
                            </td>
                            <!-- Integrantes -->
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400 max-w-[200px] truncate" title="{{ $period->users->pluck('name')->implode(', ') }}">
                                {{ $period->users->pluck('name')->implode(', ') }}
                            </td>
                            <!-- Estado -->
                            <td class="px-6 py-4">
                                @if ($period->status === 'pending_approval')
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800">
                                        {{ __('Pendiente Aprobación') }}
                                    </span>
                                @elseif ($period->status === 'approved')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800">
                                        {{ __('Aprobado') }}
                                    </span>
                                @elseif ($period->status === 'pending_cancellation')
                                    <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-800 ring-1 ring-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:ring-rose-800">
                                        {{ __('Baja Pendiente') }}
                                    </span>
                                @elseif ($period->status === 'cancelled')
                                    <span class="inline-flex rounded-full bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-800 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700">
                                        {{ __('Dado de Baja') }}
                                    </span>
                                @endif
                            </td>
                            <!-- Acciones -->
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                @if ($period->creator_id === auth()->id() && in_array($period->status, ['pending_approval', 'approved']))
                                    <flux:button 
                                        size="sm" 
                                        variant="danger" 
                                        wire:click="requestCancellation({{ $period->id }})" 
                                        wire:confirm="{{ $period->status === 'approved' ? __('¿Estás seguro de que deseas solicitar la baja de esta ronda aprobada?') : __('¿Deseas eliminar esta ronda pendiente?') }}"
                                        class="cursor-pointer"
                                    >
                                        {{ $period->status === 'approved' ? __('Solicitar Baja') : __('Eliminar') }}
                                    </flux:button>
                                @else
                                    <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                        <flux:icon.calendar class="size-6" />
                                    </div>
                                    <p class="mt-3 font-semibold text-slate-900 dark:text-slate-100">{{ __('No hay rondas registradas') }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Crea una nueva ronda o espera a ser vinculado a una.') }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($periods->hasPages())
            <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-4">
                {{ $periods->links() }}
            </div>
        @endif
    </section>

    <!-- Modal to Create Billing Period -->
    <flux:modal name="create-period-modal" class="max-w-xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Nueva Ronda de Facturación') }}</flux:heading>
                <flux:subheading>
                    {{ __('Define el periodo de tiempo, el monto máximo y los prestadores autorizados para subir facturas.') }}
                </flux:subheading>
            </div>

            <!-- Date fields -->
            <div class="grid grid-cols-2 gap-4">
                <flux:input 
                    type="date" 
                    wire:model="start_date" 
                    :label="__('Fecha de Inicio')" 
                    required 
                />

                <flux:input 
                    type="date" 
                    wire:model="end_date" 
                    :label="__('Fecha de Fin')" 
                    required 
                />
            </div>

            <!-- Cap amount -->
            <flux:input 
                type="number" 
                step="0.01" 
                min="0.01" 
                wire:model="max_amount" 
                :label="__('Monto Máximo Permitido (Tope)')" 
                placeholder="$ 0.00" 
                required 
            />

            <!-- Select Providers -->
            <div class="space-y-3">
                <label class="block text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ __('Vincular Prestadores') }}
                </label>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Selecciona los prestadores que podrán facturar en esta ronda. Tú quedarás vinculado automáticamente.') }}
                </p>

                <!-- Checkbox Table -->
                <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800 max-h-60 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="w-10 px-4 py-2"></th>
                                <th class="px-4 py-2">{{ __('Nombre') }}</th>
                                <th class="px-4 py-2">{{ __('Email') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <!-- Show Creator as auto-linked -->
                            <tr class="bg-slate-50/50 dark:bg-slate-800/20">
                                <td class="px-4 py-2.5 text-center">
                                    <flux:icon.check class="size-4 mx-auto text-emerald-600 dark:text-emerald-400" />
                                </td>
                                <td class="px-4 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                    {{ auth()->user()->name }}
                                </td>
                                <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">
                                    {{ auth()->user()->email }} <span class="italic text-[10px] text-blue-600">({{ __('Creador') }})</span>
                                </td>
                            </tr>

                            <!-- Other Providers -->
                            @forelse ($otherPrestadores as $prestador)
                                <tr>
                                    <td class="px-4 py-2.5 text-center">
                                        <input 
                                            type="checkbox" 
                                            id="p-{{ $prestador->id }}" 
                                            value="{{ $prestador->id }}" 
                                            wire:model="selectedPrestadores" 
                                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:checked:bg-blue-600"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-900 dark:text-slate-100">
                                        <label for="p-{{ $prestador->id }}" class="cursor-pointer font-medium">
                                            {{ $prestador->name }}
                                        </label>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">
                                        {{ $prestador->email }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                        {{ __('No hay otros prestadores registrados.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @error('selectedPrestadores')
                    <p class="text-xs font-semibold text-rose-600 dark:text-rose-400">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button 
                    type="submit" 
                    variant="primary" 
                    class="bg-[#0f2f5f] hover:bg-[#173f7a] text-white dark:bg-blue-600 dark:hover:bg-blue-500"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="save">{{ __('Crear Ronda') }}</span>
                    <span wire:loading wire:target="save">{{ __('Guardando...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</main>