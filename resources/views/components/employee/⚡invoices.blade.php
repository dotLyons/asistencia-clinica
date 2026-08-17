<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\Invoice;
use App\Models\Section;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    // Form fields
    public ?int $month = null;
    public ?int $year = null;
    public ?int $section_id = null;
    public string $invoice_number = '';
    public string $issue_date = '';
    public string $amount = '';
    public $pdf; // file upload

    /**
     * Mount the component and check employee permissions.
     */
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('empleado'), 403);
        
        $this->month = (int) now()->format('n');
        $this->year = (int) now()->format('Y');
        
        // Default to user's assigned section if they have one
        $this->section_id = auth()->user()->section_id;
    }

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return [
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
     * Reset pagination pages when switching search or page index if needed.
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

        // Store PDF file on public disk inside the 'invoices' directory
        $pdfPath = $this->pdf->store('invoices', 'public');

        // Create the Invoice record
        Invoice::create([
            'user_id' => auth()->id(),
            'month' => $this->month,
            'year' => $this->year,
            'section_id' => $this->section_id,
            'invoice_number' => $this->invoice_number,
            'issue_date' => $this->issue_date,
            'amount' => $this->amount,
            'pdf_path' => $pdfPath,
        ]);

        // Reset the form fields except Month, Year, and Section for quicker sequential entries
        $this->reset(['invoice_number', 'issue_date', 'amount', 'pdf']);

        // Show success notification
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

        return [
            'sections' => Section::orderBy('name')->get(),
            'monthsList' => $monthsList,
            'invoices' => Invoice::where('user_id', auth()->id())
                ->with('section')
                ->latest()
                ->paginate(10, pageName: 'invoices-page'),
        ];
    }
};
?>

<main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-6 sm:px-8 lg:px-10">
    <!-- Header -->
    <header class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91] dark:text-blue-400">{{ __('Facturación') }}</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ __('Mis Facturas') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                {{ __('Carga tus comprobantes de asistencia mensual por sector y consulta tu historial.') }}
            </p>
        </div>
    </header>

    <!-- Layout Grid -->
    <div class="grid gap-6 lg:grid-cols-[400px_1fr]">
        <!-- Form Column -->
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm h-fit dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100 mb-4">{{ __('Cargar Nueva Factura') }}</h2>
            
            <form wire:submit="save" class="space-y-5">
                <!-- Periodo: Mes y Año -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                        {{ __('Periodo de Asistencia') }}
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <flux:select wire:model="month" :label="__('Mes')" required>
                            @foreach ($monthsList as $num => $name)
                                <flux:select.option value="{{ $num }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="year" :label="__('Año')" required>
                            @for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--)
                                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                            @endfor
                        </flux:select>
                    </div>
                </div>

                <!-- Sector/Servicio -->
                <flux:select wire:model="section_id" :label="__('Servicio o Sector')" placeholder="{{ __('Seleccionar...') }}" required>
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
                        />

                        <div class="grid grid-cols-2 gap-3">
                            <flux:input 
                                wire:model="issue_date" 
                                type="date" 
                                :label="__('Fecha de Emisión')" 
                                required 
                            />

                            <flux:input 
                                wire:model="amount" 
                                type="number" 
                                step="0.01" 
                                min="0.01"
                                :label="__('Monto')" 
                                placeholder="{{ __('$ 0.00') }}" 
                                required 
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
                                    <input id="pdf-file-upload" name="pdf" type="file" wire:model="pdf" accept="application/pdf" class="sr-only">
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
        <section class="flex flex-col gap-4">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">{{ __('Mis Facturas Cargadas') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Historial completo de comprobantes presentados en el sistema.') }}
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
                                    
                                    <!-- Acciones (Ver/Descargar PDF) -->
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
                                                {{ __('Usa el formulario de la izquierda para subir tu primer comprobante.') }}
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
</main>