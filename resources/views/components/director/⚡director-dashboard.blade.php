<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\BillingPeriod;
use App\Models\Invoice;
use App\Models\User;

new class extends Component
{
    use WithPagination;

    public string $tab = 'pending'; // 'pending' or 'history'
    public ?int $selectedPeriodId = null;

    /**
     * Mount the component and verify role.
     */
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('director'), 403);
    }

    /**
     * Reset pagination when changing tabs.
     */
    public function updatingTab(): void
    {
        $this->resetPage();
    }

    /**
     * Approve a billing period.
     */
    public function approvePeriod(int $id): void
    {
        $period = BillingPeriod::findOrFail($id);
        
        if ($period->status === 'pending_approval') {
            $period->status = 'approved';
            $period->save();
            \Flux\Flux::toast(variant: 'success', text: __('Ronda de facturación aprobada con éxito.'));
        }
    }

    /**
     * Approve the cancellation request of a billing period.
     */
    public function approveCancellation(int $id): void
    {
        $period = BillingPeriod::findOrFail($id);
        
        if ($period->status === 'pending_cancellation') {
            $period->status = 'cancelled';
            $period->save();
            \Flux\Flux::toast(variant: 'success', text: __('Baja de la ronda de facturación aprobada.'));
        }
    }

    /**
     * Select a period to view details.
     */
    public function selectPeriod(?int $id): void
    {
        $this->selectedPeriodId = $id;
    }

    /**
     * Provide data to the view.
     */
    public function with(): array
    {
        // 1. Fetch periods based on tab
        $periodsQuery = BillingPeriod::with(['creator', 'users']);

        if ($this->tab === 'pending') {
            $periodsQuery->whereIn('status', ['pending_approval', 'pending_cancellation']);
        }

        $periods = $periodsQuery->latest()->paginate(10);

        // 2. Fetch details for selected period
        $selectedPeriod = null;
        $linkedPrestadoresData = [];

        if ($this->selectedPeriodId) {
            $selectedPeriod = BillingPeriod::with(['creator', 'users'])->find($this->selectedPeriodId);
            if ($selectedPeriod) {
                foreach ($selectedPeriod->users as $prestador) {
                    // Get all invoices of this provider in this period
                    $invoices = Invoice::where('billing_period_id', $this->selectedPeriodId)
                        ->where('user_id', $prestador->id)
                        ->with('section')
                        ->get();

                    $totalUploaded = $invoices->sum('amount');

                    $linkedPrestadoresData[] = [
                        'user' => $prestador,
                        'total_uploaded' => $totalUploaded,
                        'invoices' => $invoices,
                    ];
                }
            }
        }

        return [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'linkedPrestadoresData' => $linkedPrestadoresData,
        ];
    }
};
?>

<main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-6 sm:px-8 lg:px-10">
    <!-- Header -->
    <header class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91] dark:text-blue-400">{{ __('Dirección') }}</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ __('Panel del Director') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                {{ __('Revisa, aprueba o da de baja las rondas de facturación y realiza el seguimiento de montos y archivos subidos por cada prestador.') }}
            </p>
        </div>
    </header>

    <!-- Tabs and Stats Grid -->
    <div class="flex flex-col gap-6">
        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-200 dark:border-slate-800">
            <button 
                wire:click="$set('tab', 'pending')" 
                @class([
                    'px-4 py-2 text-sm font-medium border-b-2 transition-colors cursor-pointer',
                    'border-[#1e4f91] text-[#1e4f91] dark:border-blue-400 dark:text-blue-400' => $tab === 'pending',
                    'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $tab !== 'pending',
                ])
            >
                {{ __('Solicitudes Pendientes') }}
            </button>
            <button 
                wire:click="$set('tab', 'history')" 
                @class([
                    'px-4 py-2 text-sm font-medium border-b-2 transition-colors cursor-pointer',
                    'border-[#1e4f91] text-[#1e4f91] dark:border-blue-400 dark:text-blue-400' => $tab === 'history',
                    'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $tab !== 'history',
                ])
            >
                {{ __('Historial de Rondas') }}
            </button>
        </div>

        <!-- Period List Table -->
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">
                    {{ $tab === 'pending' ? __('Rondas esperando Aprobación o Baja') : __('Todas las Rondas de Facturación') }}
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="px-6 py-3">{{ __('Ronda ID') }}</th>
                            <th class="px-6 py-3">{{ __('Fecha Inicio') }}</th>
                            <th class="px-6 py-3">{{ __('Fecha Fin') }}</th>
                            <th class="px-6 py-3">{{ __('Monto Máximo (Tope)') }}</th>
                            <th class="px-6 py-3">{{ __('Prestador Jefe (Creador)') }}</th>
                            <th class="px-6 py-3">{{ __('Integrantes') }}</th>
                            <th class="px-6 py-3">{{ __('Estado') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($periods as $period)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-6 py-4 font-semibold text-slate-950 dark:text-slate-100">
                                    #{{ $period->id }}
                                </td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                    {{ $period->start_date->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                    {{ $period->end_date->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-slate-100">
                                    ${{ number_format($period->max_amount, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                    {{ $period->creator->name }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-400 max-w-[200px] truncate" title="{{ $period->users->pluck('name')->implode(', ') }}">
                                    {{ $period->users->pluck('name')->implode(', ') }}
                                </td>
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
                                <td class="px-6 py-4 text-right space-x-1 whitespace-nowrap">
                                    <!-- Detail Tracking Button -->
                                    <flux:modal.trigger name="details-modal">
                                        <flux:button 
                                            size="sm" 
                                            variant="filled" 
                                            icon="eye"
                                            wire:click="selectPeriod({{ $period->id }})"
                                            class="cursor-pointer bg-[#0f2f5f] text-white hover:bg-[#173f7a] dark:bg-blue-600 dark:hover:bg-blue-500"
                                        >
                                            {{ __('Detalles') }}
                                        </flux:button>
                                    </flux:modal.trigger>

                                    <!-- Approval Buttons -->
                                    @if ($period->status === 'pending_approval')
                                        <flux:button 
                                            size="sm" 
                                            variant="primary" 
                                            wire:click="approvePeriod({{ $period->id }})"
                                            class="cursor-pointer bg-emerald-600 hover:bg-emerald-500 text-white dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                        >
                                            {{ __('Aprobar') }}
                                        </flux:button>
                                    @elseif ($period->status === 'pending_cancellation')
                                        <flux:button 
                                            size="sm" 
                                            variant="danger" 
                                            wire:click="approveCancellation({{ $period->id }})"
                                            class="cursor-pointer"
                                        >
                                            {{ __('Aceptar Baja') }}
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                    {{ __('No se encontraron rondas de facturación.') }}
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
    </div>

    <!-- Modal for Billing Period Details & Trackings -->
    <flux:modal name="details-modal" class="max-w-4xl" wire:ignore.self>
        @if ($selectedPeriod)
            <div class="space-y-6">
                <!-- Header of Details -->
                <div class="border-b border-slate-100 pb-4 dark:border-slate-800">
                    <flux:heading size="lg">{{ __('Detalles de Ronda') }} #{{ $selectedPeriod->id }}</flux:heading>
                    <flux:subheading>
                        {{ __('Creado por prestador jefe:') }} <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $selectedPeriod->creator->name }}</span>
                    </flux:subheading>
                    
                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Inicio') }}</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $selectedPeriod->start_date->format('d/m/Y') }}</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Fin') }}</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $selectedPeriod->end_date->format('d/m/Y') }}</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Monto Límite (Cap)') }}</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">${{ number_format($selectedPeriod->max_amount, 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Estado') }}</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 capitalize">{{ $selectedPeriod->status }}</p>
                        </div>
                    </div>
                </div>

                <!-- Provider Tracking List -->
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 mb-4">{{ __('Seguimiento de Prestadores') }}</h3>
                    
                    <div class="space-y-4">
                        @foreach ($linkedPrestadoresData as $data)
                            @php
                                $progressPercent = min(100, ($data['total_uploaded'] / $selectedPeriod->max_amount) * 100);
                            @endphp
                            <div class="rounded-xl border border-slate-200 p-5 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-900/30">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-950 dark:text-slate-100">{{ $data['user']->name }}</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $data['user']->email }}</p>
                                    </div>
                                    
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-200">
                                                ${{ number_format($data['total_uploaded'], 2, ',', '.') }} / ${{ number_format($selectedPeriod->max_amount, 2, ',', '.') }}
                                            </p>
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500">
                                                {{ __('Completado:') }} {{ round($progressPercent, 1) }}%
                                            </p>
                                        </div>

                                        <!-- Merged PDF download button -->
                                        <flux:button 
                                            size="sm" 
                                            variant="filled" 
                                            icon="arrow-down-tray" 
                                            as="a"
                                            href="{{ route('director.download-invoices', [$selectedPeriod->id, $data['user']->id]) }}"
                                            target="_blank"
                                            class="cursor-pointer bg-[#0f2f5f] text-white hover:bg-[#173f7a] dark:bg-blue-600 dark:hover:bg-blue-500"
                                            title="{{ __('Descargar todo (PDF fusionado)') }}"
                                            :disabled="$data['invoices']->isEmpty()"
                                        >
                                            {{ __('Descargar Todo') }}
                                        </flux:button>
                                    </div>
                                </div>

                                <!-- Progress bar -->
                                <div class="mt-3 relative h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden border border-slate-200/40">
                                    <div 
                                        class="absolute top-0 bottom-0 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full"
                                        style="width: {{ $progressPercent }}%;"
                                    ></div>
                                </div>

                                <!-- Individual uploaded documents collapsible/list -->
                                @if (count($data['invoices']) > 0)
                                    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                                        <p class="text-[10px] font-semibold uppercase text-slate-400 dark:text-slate-500 mb-2">{{ __('Archivos cargados:') }}</p>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ($data['invoices'] as $inv)
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2.5 text-xs dark:border-slate-800 dark:bg-slate-900">
                                                    <div class="min-w-0">
                                                        <p class="font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $inv->invoice_number }}</p>
                                                        <p class="text-[10px] text-slate-500 dark:text-slate-400">
                                                            {{ $inv->issue_date->format('d/m/Y') }} — ${{ number_format($inv->amount, 2, ',', '.') }}
                                                        </p>
                                                    </div>
                                                    <flux:button 
                                                        size="xs" 
                                                        variant="ghost" 
                                                        icon="eye"
                                                        as="a"
                                                        href="{{ route('invoices.download', $inv) }}"
                                                        target="_blank"
                                                    />
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <p class="mt-3 text-xs text-center text-slate-400 dark:text-slate-500 italic">
                                        {{ __('No se han cargado facturas todavía para este prestador.') }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Close -->
                <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                    <flux:modal.close>
                        <flux:button>{{ __('Cerrar') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</main>