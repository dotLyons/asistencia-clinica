<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $tab = 'employees'; // 'employees' or 'history'
    public ?int $selectedEmployeeId = null;
    public bool $showHistoryModal = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => 'employees'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage('employees-page');
        $this->resetPage('history-page');
    }

    public function selectEmployee(int $id): void
    {
        $this->selectedEmployeeId = $id;
        $this->showHistoryModal = true;
    }

    public function getSelectedEmployeeProperty(): ?User
    {
        if (! $this->selectedEmployeeId) {
            return null;
        }
        return User::with(['attendances' => function ($q) {
            $q->latest('occurred_at')->latest('id');
        }])->find($this->selectedEmployeeId);
    }

    public function with(): array
    {
        // Statistics
        $totalEmployees = User::whereDoesntHave('roles', function (Builder $query) {
            $query->where('name', 'administrador');
        })->count();

        // Active today: checked in today and their last status is 'entrada'
        $activeToday = User::whereDoesntHave('roles', function (Builder $query) {
            $query->where('name', 'administrador');
        })->whereHas('attendances', function (Builder $query) {
            $query->whereDate('occurred_at', today())
                ->where('type', 'entrada')
                ->whereRaw('id = (select id from attendances as a2 where a2.user_id = attendances.user_id order by occurred_at desc, id desc limit 1)');
        })->count();

        $movementsToday = Attendance::whereDate('occurred_at', today())->count();

        // Fetch based on active tab
        if ($this->tab === 'employees') {
            $employees = User::whereDoesntHave('roles', function (Builder $query) {
                $query->where('name', 'administrador');
            })
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->withCount('attendances')
            ->with(['attendances' => function ($query) {
                $query->latest('occurred_at')->latest('id');
            }])
            ->orderBy('name')
            ->paginate(10, pageName: 'employees-page');

            $history = null;
        } else {
            $employees = null;

            $history = Attendance::with('user')
                ->whereHas('user', function (Builder $query) {
                    $query->whereDoesntHave('roles', function (Builder $q) {
                        $q->where('name', 'administrador');
                    });
                })
                ->when($this->search, function (Builder $query) {
                    $query->whereHas('user', function (Builder $q) {
                        $q->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
                })
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(15, pageName: 'history-page');
        }

        return [
            'totalEmployees' => $totalEmployees,
            'activeToday' => $activeToday,
            'movementsToday' => $movementsToday,
            'employees' => $employees,
            'history' => $history,
            'selectedEmployee' => $this->getSelectedEmployeeProperty(),
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <header class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91]">{{ __('Panel de Administración') }}</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ __('Control Global de Asistencia') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">{{ __('Visualiza todos los empleados, sus movimientos recientes y el historial completo de asistencia.') }}</p>
        </div>
    </header>

    <!-- Stats -->
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex size-11 items-center justify-center rounded-xl bg-blue-50 text-[#0f2f5f] shadow-2xs">
                    <flux:icon.users class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Empleados') }}</p>
                    <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ $totalEmployees }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 shadow-2xs">
                    <flux:icon.user class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Activos Hoy') }}</p>
                    <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ $activeToday }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600 shadow-2xs">
                    <flux:icon.clock class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Registros Hoy') }}</p>
                    <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ $movementsToday }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Tabs -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between rounded-xl border border-slate-200 bg-white/95 p-4 shadow-sm">
        <div class="relative flex-1 max-w-md">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Buscar por nombre o correo...') }}" 
                icon="magnifying-glass"
            />
        </div>

        <div class="flex border-b border-slate-200 self-start lg:self-auto">
            <button wire:click="$set('tab', 'employees')" @class([
                'px-4 py-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer',
                'border-[#0f2f5f] text-[#0f2f5f]' => $tab === 'employees',
                'border-transparent text-slate-500 hover:text-slate-700' => $tab !== 'employees'
            ])>
                {{ __('Empleados') }}
            </button>
            <button wire:click="$set('tab', 'history')" @class([
                'px-4 py-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer',
                'border-[#0f2f5f] text-[#0f2f5f]' => $tab === 'history',
                'border-transparent text-slate-500 hover:text-slate-700' => $tab !== 'history'
            ])>
                {{ __('Historial General') }}
            </button>
        </div>
    </div>

    <!-- Content -->
    @if ($tab === 'employees')
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('Empleado') }}</th>
                            <th class="px-6 py-3">{{ __('Correo') }}</th>
                            <th class="px-6 py-3">{{ __('Total Movimientos') }}</th>
                            <th class="px-6 py-3">{{ __('Último Registro') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($employees as $employee)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <flux:avatar
                                            :name="$employee->name"
                                            :initials="$employee->initials()"
                                            class="bg-[#0f2f5f] text-white size-9 shadow-2xs"
                                        />
                                        <span class="font-medium text-slate-900">{{ $employee->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $employee->email }}</td>
                                <td class="px-6 py-4 text-slate-600 font-semibold">{{ $employee->attendances_count }}</td>
                                <td class="px-6 py-4">
                                    @if ($last = $employee->attendances->first())
                                        <div class="flex items-center gap-2">
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                                'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' => $last->type === 'entrada',
                                                'bg-amber-50 text-amber-700 ring-1 ring-amber-200' => $last->type === 'salida',
                                            ])>
                                                {{ str($last->type)->title() }}
                                            </span>
                                            <span class="text-xs text-slate-500">
                                                {{ $last->occurred_at->format('Y-m-d H:i') }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="selectEmployee({{ $employee->id }})">
                                        {{ __('Ver Historial') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                    {{ __('No se encontraron empleados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($employees && $employees->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $employees->links() }}
                </div>
            @endif
        </section>
    @else
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('Empleado') }}</th>
                            <th class="px-6 py-3">{{ __('Correo') }}</th>
                            <th class="px-6 py-3">{{ __('Tipo') }}</th>
                            <th class="px-6 py-3">{{ __('Fecha') }}</th>
                            <th class="px-6 py-3">{{ __('Hora') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($history as $record)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-6 py-4 font-medium text-slate-900">
                                    <div class="flex items-center gap-3">
                                        <flux:avatar
                                            :name="$record->user->name"
                                            :initials="$record->user->initials()"
                                            class="bg-[#0f2f5f] text-white size-8 shadow-2xs"
                                        />
                                        <span>{{ $record->user->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $record->user->email }}</td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' => $record->type === 'entrada',
                                        'bg-amber-50 text-amber-700 ring-1 ring-amber-200' => $record->type === 'salida',
                                    ])>
                                        {{ str($record->type)->title() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $record->occurred_at->format('Y-m-d') }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $record->occurred_at->format('H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                    {{ __('No hay registros de asistencia.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($history && $history->hasPages())
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $history->links() }}
                </div>
            @endif
        </section>
    @endif

    <!-- Detail Modal -->
    <flux:modal name="employee-history-modal" wire:model="showHistoryModal" class="w-full max-w-2xl md:min-w-[600px]">
        @if ($selectedEmployee)
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <flux:avatar
                        :name="$selectedEmployee->name"
                        :initials="$selectedEmployee->initials()"
                        class="bg-[#0f2f5f] text-white size-12 shadow-2xs"
                    />
                    <div>
                        <flux:heading size="lg">{{ $selectedEmployee->name }}</flux:heading>
                        <flux:subheading>{{ $selectedEmployee->email }}</flux:subheading>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <h4 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Historial de Asistencia') }}</h4>
                    <div class="max-h-[300px] overflow-y-auto rounded-lg border border-slate-200">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-2">{{ __('Tipo') }}</th>
                                    <th class="px-4 py-2">{{ __('Fecha') }}</th>
                                    <th class="px-4 py-2">{{ __('Hora') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($selectedEmployee->attendances as $record)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="px-4 py-2.5">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' => $record->type === 'entrada',
                                                'bg-amber-50 text-amber-700 ring-1 ring-amber-200' => $record->type === 'salida',
                                            ])>
                                                {{ str($record->type)->title() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-600 font-medium">
                                            {{ $record->occurred_at->format('Y-m-d') }}
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-600">
                                            {{ $record->occurred_at->format('H:i:s') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-slate-400 text-xs">
                                            {{ __('Sin registros de asistencia.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cerrar') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>