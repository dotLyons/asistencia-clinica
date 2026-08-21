<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\Invoice;
use App\Models\Section;
use App\Models\BillingPeriod;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    // Selected Billing Period
    public ?int $billing_period_id = null;

    // Form fields
    public ?int $month = null;
    public ?int $year = null;
    public ?int $section_id = null;
    public string $invoice_number = '';
    public string $issue_date = '';
    public string $amount = '';
    public $pdf; // file upload

    /**
     * Mount the component and check provider permissions.
     */
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('prestador'), 403);
        
        $this->month = (int) now()->format('n');
        $this->year = (int) now()->format('Y');
        
        // Find default/first approved billing period to select
        $firstApproved = BillingPeriod::whereHas('users', function ($q) {
                $q->where('users.id', auth()->id());
            })
            ->where('status', 'approved')
            ->first();

        if ($firstApproved) {
            $this->billing_period_id = $firstApproved->id;
        }
    }

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return [
            'billing_period_id' => 'required|exists:billing_periods,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2035',
            'section_id' => 'required|exists:sections,id',
            'invoice_number' => 'required|string|max:100',
            'issue_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01',
            'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB
        ];
    }

    /**
     * Custom validation attributes.
     */
    protected function validationAttributes(): array
    {
        return [
            'billing_period_id' => __('periodo de facturación'),
            'month' => __('mes'),
            'year' => __('año'),
            'section_id' => __('servicio o sector'),
            'invoice_number' => __('número de factura'),
            'issue_date' => __('fecha de emisión'),
            'amount' => __('monto'),
            'pdf' => __('archivo PDF'),
        ];
    }

    /**
     * Realtime validation for PDF.
     */
    public function updatedPdf(): void
    {
        $this->validateOnly('pdf');
    }

    /**
     * Save the invoice and store the file.
     */
    public function save(): void
    {
        $validated = $this->validate();

        // 1. Verify the selected period is active/approved
        $billingPeriod = BillingPeriod::whereHas('users', function ($q) {
                $q->where('users.id', auth()->id());
            })
            ->where('id', $this->billing_period_id)
            ->first();

        if (!$billingPeriod || $billingPeriod->status !== 'approved') {
            $this->addError('billing_period_id', __('El periodo de facturación seleccionado no está aprobado o no estás vinculado a él.'));
            return;
        }

        // 2. Validate amount limits (individual caps)
        $totalInvoiced = Invoice::where('billing_period_id', $this->billing_period_id)
            ->where('user_id', auth()->id())
            ->sum('amount');

        $remainingLimit = (float)$billingPeriod->max_amount - (float)$totalInvoiced;
        $incomingAmount = (float)$this->amount;

        if ($incomingAmount > $remainingLimit) {
            $this->addError('amount', __('El monto ingresado supera el tope disponible de la ronda ($ :remaining).', [
                'remaining' => number_format($remainingLimit, 2, ',', '.')
            ]));
            return;
        }

        // Store PDF file on public disk
        $pdfPath = $this->pdf->store('invoices', 'public');

        // Create the Invoice record linked to the Billing Period
        Invoice::create([
            'user_id' => auth()->id(),
            'month' => $this->month,
            'year' => $this->year,
            'section_id' => $this->section_id,
            'invoice_number' => $this->invoice_number,
            'issue_date' => $this->issue_date,
            'amount' => $incomingAmount,
            'pdf_path' => $pdfPath,
            'billing_period_id' => $this->billing_period_id,
        ]);

        // Reset inputs
        $this->reset(['invoice_number', 'issue_date', 'amount', 'pdf']);

        // Success toast
        \Flux\Flux::toast(variant: 'success', text: __('Factura cargada con éxito.'));
    }

    /**
     * Provide data to the view.
     */
    public function with(): array
    {
        $monthsList = [
            1 => __('Enero'),
            2 => __('Febrero'),
            3 => __('Marzo'),
            4 => __('Abril'),
            5 => __('Mayo'),
            6 => __('Junio'),
            7 => __('Julio'),
            8 => __('Agosto'),
            9 => __('Septiembre'),
            10 => __('Octubre'),
            11 => __('Noviembre'),
            12 => __('Diciembre'),
        ];

        // Fetch only approved billing periods to which current provider is linked
        $linkedPeriods = BillingPeriod::whereHas('users', function ($q) {
                $q->where('users.id', auth()->id());
            })
            ->where('status', 'approved')
            ->orderBy('id', 'desc')
            ->get();

        // Calculate limits if a period is selected
        $selectedPeriod = null;
        $maxAmount = 0.0;
        $uploadedAmount = 0.0;
        $remainingAmount = 0.0;

        if ($this->billing_period_id) {
            $selectedPeriod = BillingPeriod::find($this->billing_period_id);
            if ($selectedPeriod) {
                $maxAmount = (float)$selectedPeriod->max_amount;
                $uploadedAmount = (float)Invoice::where('billing_period_id', $this->billing_period_id)
                    ->where('user_id', auth()->id())
                    ->sum('amount');
                $remainingAmount = max(0.0, $maxAmount - $uploadedAmount);
            }
        }

        // Fetch invoices for selected billing period
        $invoices = Invoice::where('user_id', auth()->id())
            ->where('billing_period_id', $this->billing_period_id)
            ->with('section')
            ->latest()
            ->paginate(10, pageName: 'invoices-page');

        return [
            'sections' => Section::orderBy('name')->get(),
            'monthsList' => $monthsList,
            'linkedPeriods' => $linkedPeriods,
            'selectedPeriod' => $selectedPeriod,
            'maxAmount' => $maxAmount,
            'uploadedAmount' => $uploadedAmount,
            'remainingAmount' => $remainingAmount,
            'invoices' => $invoices,
        ];
    }
};
?>

<main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-6 sm:px-8 lg:px-10">
    <!-- Header -->
    <header class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91] dark:text-blue-400">{{ __('Facturación') }}</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ __('Subir Mis Facturas') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                {{ __('Selecciona una ronda de facturación activa para cargar tus comprobantes.') }}
            </p>
        </div>
    </header>

    <!-- Period Selection Bar -->
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center justify-between">
            <div class="w-full sm:max-w-md">
                <flux:select wire:model.live="billing_period_id" :label="__('Seleccionar Ronda de Facturación Aprobada')" placeholder="{{ __('Seleccionar ronda...') }}">
                    @foreach ($linkedPeriods as $lp)
                        <flux:select.option value="{{ $lp->id }}">
                            Ronda #{{ $lp->id }} ({{ $lp->start_date->format('d/m/Y') }} - {{ $lp->end_date->format('d/m/Y') }}) - Tope: ${{ number_format($lp->max_amount, 0, ',', '.') }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Stats/Allowance -->
            @if ($selectedPeriod)
                <div class="grid grid-cols-3 gap-4 border-t border-slate-100 dark:border-slate-800 sm:border-t-0 pt-4 sm:pt-0">
                    <div class="text-center sm:text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Tope de Ronda') }}</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${{ number_format($maxAmount, 2, ',', '.') }}</p>
                    </div>
                    <div class="text-center sm:text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Cargado por ti') }}</p>
                        <p class="text-sm font-bold text-blue-600 dark:text-blue-400">${{ number_format($uploadedAmount, 2, ',', '.') }}</p>
                    </div>
                    <div class="text-center sm:text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Disponible') }}</p>
                        <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">${{ number_format($remainingAmount, 2, ',', '.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @if ($billing_period_id && $selectedPeriod)
        <!-- Layout Grid -->
        <div class="grid gap-6 lg:grid-cols-[400px_1fr]">
            <!-- Form Column -->
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm h-fit dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100 mb-4">{{ __('Cargar Nueva Factura') }}</h2>
                
                @if ($remainingAmount <= 0)
                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 mb-4 dark:bg-amber-950/40 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300">
                        {{ __('Has completado el objetivo/tope de facturación para esta ronda. No puedes cargar más facturas.') }}
                    </div>
                @endif

                <form wire:submit="save" class="space-y-5">
                    <!-- Periodo: Mes y Año -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                            {{ __('Periodo de Prestación') }}
                        </h3>
                        <div class="grid grid-cols-2 gap-3">
                            <flux:select wire:model="month" :label="__('Mes')" required :disabled="$remainingAmount <= 0">
                                @foreach ($monthsList as $num => $name)
                                    <flux:select.option value="{{ $num }}">{{ $name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="year" :label="__('Año')" required :disabled="$remainingAmount <= 0">
                                @for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--)
                                    <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                                @endfor
                            </flux:select>
                        </div>
                    </div>

                    <!-- Sector/Servicio -->
                    <flux:select wire:model="section_id" :label="__('Servicio o Sector')" placeholder="{{ __('Seleccionar...') }}" required :disabled="$remainingAmount <= 0">
                        @foreach ($sections as $sec)
                            <flux:select.option value="{{ $sec->id }}">{{ $sec->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <hr class="border-slate-100 dark:border-slate-800" />

                    <!-- Factura Electrónica -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
                            {{ __('Detalles de Factura Electrónica') }}
                        </h3>
                        
                        <div class="space-y-4">
                            <flux:input 
                                wire:model="invoice_number" 
                                :label="__('Número de Factura')" 
                                placeholder="{{ __('Ej: 0001-00001234') }}" 
                                required 
                                :disabled="$remainingAmount <= 0"
                            />

                            <div class="grid grid-cols-2 gap-3">
                                <flux:input 
                                    wire:model="issue_date" 
                                    type="date" 
                                    :label="__('Fecha de Emisión')" 
                                    required 
                                    :disabled="$remainingAmount <= 0"
                                />

                                <flux:input 
                                    wire:model="amount" 
                                    type="number" 
                                    step="0.01" 
                                    min="0.01"
                                    max="{{ $remainingAmount }}"
                                    :label="__('Monto')" 
                                    placeholder="{{ __('$ 0.00') }}" 
                                    required 
                                    :disabled="$remainingAmount <= 0"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- PDF Upload Zone -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ __('Subir PDF de Factura') }}
                        </label>
                        
                        <div 
                            class="relative flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 px-6 py-6 text-center hover:bg-slate-50/50 dark:border-slate-800 dark:hover:bg-slate-800/40 transition-colors"
                        >
                            <div class="space-y-1">
                                <svg class="mx-auto size-10 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                
                                <div class="flex text-xs justify-center text-slate-600 dark:text-slate-400">
                                    <label for="pdf-file-upload" class="relative cursor-pointer rounded-md font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400 focus-within:outline-hidden">
                                        <span>{{ __('Selecciona un archivo') }}</span>
                                        <input id="pdf-file-upload" name="pdf" type="file" wire:model="pdf" accept="application/pdf" class="sr-only" :disabled="$remainingAmount <= 0">
                                    </label>
                                    <span class="pl-1">{{ __('o arrástralo aquí') }}</span>
                                </div>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Solo PDF (máx. 10MB)') }}</p>
                            </div>

                            <!-- Progress / State Indicators -->
                            <div wire:loading wire:target="pdf" class="absolute inset-0 bg-white/80 dark:bg-slate-900/80 flex items-center justify-center rounded-lg">
                                <div class="flex items-center gap-2 text-sm text-[#0f2f5f] dark:text-blue-400 font-medium">
                                    <svg class="animate-spin size-4 text-current" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>{{ __('Subiendo archivo...') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Selected File State -->
                        @if ($pdf && !$errors->has('pdf'))
                            <div class="flex items-center gap-2 rounded-lg bg-emerald-50 border border-emerald-200 p-2.5 dark:bg-emerald-950/40 dark:border-emerald-900/40">
                                <flux:icon.check class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <div class="flex-1 min-w-0 text-xs">
                                    <p class="font-medium text-emerald-800 dark:text-emerald-300 truncate">
                                        {{ $pdf->getClientOriginalName() }}
                                    </p>
                                    <p class="text-emerald-600 dark:text-emerald-400 mt-0.5">
                                        {{ round($pdf->getSize() / 1024 / 1024, 2) }} MB
                                    </p>
                                </div>
                            </div>
                        @endif

                        @error('pdf')
                            <p class="text-xs font-semibold text-rose-600 dark:text-rose-400 mt-1">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <flux:button 
                        variant="primary" 
                        type="submit" 
                        class="w-full cursor-pointer bg-[#0f2f5f] hover:bg-[#173f7a] text-white dark:bg-blue-600 dark:hover:bg-blue-500"
                        wire:loading.attr="disabled"
                        :disabled="$remainingAmount <= 0"
                    >
                        <span wire:loading.remove wire:target="save">{{ __('Cargar Factura') }}</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin size-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ __('Guardando...') }}</span>
                        </span>
                    </flux:button>
                </form>
            </section>

            <!-- History Column -->
            <section class="flex flex-col gap-4 min-w-0">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">{{ __('Mis Facturas Cargadas en esta Ronda') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Historial completo de comprobantes presentados en este periodo.') }}
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3">{{ __('Periodo') }}</th>
                                    <th class="px-6 py-3">{{ __('Sector') }}</th>
                                    <th class="px-6 py-3">{{ __('Nº Factura') }}</th>
                                    <th class="px-6 py-3">{{ __('Emisión') }}</th>
                                    <th class="px-6 py-3">{{ __('Monto') }}</th>
                                    <th class="px-6 py-3 text-right">{{ __('Acciones') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($invoices as $invoice)
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <!-- Periodo -->
                                        <td class="px-6 py-4 font-medium text-slate-950 dark:text-slate-100">
                                            {{ $monthsList[$invoice->month] ?? $invoice->month }} {{ $invoice->year }}
                                        </td>
                                        
                                        <!-- Sector -->
                                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                            {{ $invoice->section?->name ?? '—' }}
                                        </td>
                                        
                                        <!-- Nº Factura -->
                                        <td class="px-6 py-4 font-mono text-xs text-slate-500 dark:text-slate-400">
                                            {{ $invoice->invoice_number }}
                                        </td>
                                        
                                        <!-- Fecha Emisión -->
                                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                            {{ $invoice->issue_date->format('d/m/Y') }}
                                        </td>
                                        
                                        <!-- Monto -->
                                        <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-200">
                                            ${{ number_format($invoice->amount, 2, ',', '.') }}
                                        </td>
                                        
                                        <!-- Acciones (Ver PDF) -->
                                        <td class="px-6 py-4 text-right space-x-1 whitespace-nowrap">
                                            <flux:button 
                                                size="sm" 
                                                variant="filled" 
                                                icon="document-text" 
                                                as="a"
                                                href="{{ route('invoices.download', $invoice) }}"
                                                target="_blank"
                                                class="cursor-pointer bg-[#0f2f5f] text-white hover:bg-[#173f7a] dark:bg-blue-600 dark:hover:bg-blue-500"
                                                title="{{ __('Ver PDF') }}"
                                            >
                                                {{ __('Ver PDF') }}
                                            </flux:button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                            <div class="mx-auto max-w-sm">
                                                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                    </svg>
                                                </div>
                                                <p class="mt-3 font-semibold text-slate-900 dark:text-slate-100">{{ __('No hay facturas cargadas') }}</p>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                    {{ __('Usa el formulario de la izquierda para subir tu primer comprobante para esta ronda.') }}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($invoices && $invoices->hasPages())
                        <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-4">
                            {{ $invoices->links() }}
                        </div>
                    @endif
                </div>
            </section>
        </div>
    @else
        <!-- No period selected state -->
        <section class="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto max-w-md">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                    <flux:icon.calendar class="size-6" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('Selecciona una Ronda de Facturación') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Para cargar facturas, debes estar vinculado a un periodo de facturación aprobado por el director. Selecciónalo en el menú desplegable superior.') }}
                </p>
            </div>
        </section>
    @endif
</main>