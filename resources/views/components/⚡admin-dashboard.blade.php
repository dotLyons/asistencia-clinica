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

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => 'employees'],
        'selectedEmployeeId' => ['except' => null],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage('employees-page');
        $this->resetPage('history-page');
    }

    public function selectEmployee(int $id): void
    {
        $this->selectedEmployeeId = $id;
        $this->resetPage('employee-attendances-page');
    }

    private function getHoursWorked(User $user, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): float
    {
        $attendances = $user->attendances()
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('occurred_at', 'asc')
            ->get();

        $totalSeconds = 0;
        $lastEntrada = null;

        foreach ($attendances as $attendance) {
            if ($attendance->type === 'entrada') {
                $lastEntrada = $attendance->occurred_at;
            } elseif ($attendance->type === 'salida' && $lastEntrada) {
                $totalSeconds += $attendance->occurred_at->diffInSeconds($lastEntrada);
                $lastEntrada = null;
            }
        }

        // If currently active (last event was entry and it falls inside the range), add time up to now()
        if ($lastEntrada && $lastEntrada->between($start, $end)) {
            $totalSeconds += now()->diffInSeconds($lastEntrada);
        }

        return round($totalSeconds / 3600, 1);
    }

    public function with(): array
    {
        if ($this->selectedEmployeeId) {
            $selectedEmployee = User::find($this->selectedEmployeeId);
            $isActive = false;
            $lastAttendance = null;
            $hoursThisWeek = 0.0;
            $hoursThisMonth = 0.0;
            $employeeAttendances = null;

            if ($selectedEmployee) {
                $lastAttendance = $selectedEmployee->attendances()
                    ->latest('occurred_at')
                    ->latest('id')
                    ->first();
                $isActive = $lastAttendance && $lastAttendance->type === 'entrada';

                $hoursThisWeek = $this->getHoursWorked($selectedEmployee, now()->startOfWeek(), now()->endOfWeek());
                $hoursThisMonth = $this->getHoursWorked($selectedEmployee, now()->startOfMonth(), now()->endOfMonth());

                $employeeAttendances = $selectedEmployee->attendances()
                    ->latest('occurred_at')
                    ->latest('id')
                    ->paginate(10, pageName: 'employee-attendances-page');
            }

            return [
                'selectedEmployee' => $selectedEmployee,
                'isActive' => $isActive,
                'lastAttendance' => $lastAttendance,
                'hoursThisWeek' => $hoursThisWeek,
                'hoursThisMonth' => $hoursThisMonth,
                'employeeAttendances' => $employeeAttendances,
            ];
        }

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
            'selectedEmployee' => null,
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    @if ($selectedEmployeeId && $selectedEmployee)
        <!-- Profile View -->
        <div class="flex flex-col gap-6">
            <!-- Header / Navigation -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <flux:avatar
                        :name="$selectedEmployee->name"
                        :initials="$selectedEmployee->initials()"
                        class="bg-[#0f2f5f] text-white size-14 shadow-xs"
                    />
                    <div>
                        <h1 class="text-2xl font-bold text-slate-950">{{ $selectedEmployee->name }}</h1>
                        <p class="text-sm text-slate-500">{{ $selectedEmployee->email }}</p>
                    </div>
                </div>
                
                <flux:button icon="arrow-left" variant="filled" wire:click="$set('selectedEmployeeId', null)" class="self-start sm:self-auto cursor-pointer">
                    {{ __('Volver al listado') }}
                </flux:button>
            </div>

            <!-- Metric Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <!-- Card 1: Estado Actual -->
                <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                    <div class="flex items-center gap-4">
                        @if ($isActive)
                            <div class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 shadow-2xs">
                                <flux:icon.check class="size-5" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Estado Actual') }}</p>
                                <span class="inline-flex rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-2.5 py-0.5 text-xs font-semibold mt-1">
                                    {{ __('Activo') }}
                                </span>
                                @if ($lastAttendance)
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ __('Entrada: :time', ['time' => $lastAttendance->occurred_at->format('H:i')]) }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="flex size-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400 shadow-2xs">
                                <flux:icon.user class="size-5" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Estado Actual') }}</p>
                                <span class="inline-flex rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200 px-2.5 py-0.5 text-xs font-semibold mt-1">
                                    {{ __('Inactivo') }}
                                </span>
                                @if ($lastAttendance)
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ __('Salida: :time', ['time' => $lastAttendance->occurred_at->format('H:i')]) }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Card 2: Horas Semanales -->
                <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-blue-50 text-[#0f2f5f] shadow-2xs">
                            <flux:icon.clock class="size-5" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Horas esta semana') }}</p>
                            <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ $hoursThisWeek }} hrs</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ __('Lun a Dom') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Horas Mensuales -->
                <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600 shadow-2xs">
                            <flux:icon.clock class="size-5" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Horas este mes') }}</p>
                            <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ $hoursThisMonth }} hrs</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ now()->translatedFormat('F Y') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance History Table -->
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-950">{{ __('Historial de Fichajes') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Historial completo de entradas y salidas de este empleado.') }}</p>
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
                            @forelse ($employeeAttendances as $attendance)
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
                                    <td class="px-6 py-4 text-slate-700 font-medium">{{ $attendance->occurred_at->format('Y-m-d') }}</td>
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
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                        {{ __('Aún no hay registros de asistencia para este empleado.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($employeeAttendances && $employeeAttendances->hasPages())
                    <div class="border-t border-slate-200 px-6 py-4">
                        {{ $employeeAttendances->links() }}
                    </div>
                @endif
            </section>
        </div>
    @else
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
                                <th class="px-6 py-3">{{ __('Ubicación') }}</th>
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
                                    <td class="px-6 py-4 text-slate-700">
                                        @if ($record->latitude && $record->longitude)
                                            <a href="https://www.google.com/maps/search/?api=1&query={{ $record->latitude }},{{ $record->longitude }}" target="_blank" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 font-medium hover:underline">
                                                <svg class="size-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>{{ round($record->latitude, 4) }}, {{ round($record->longitude, 4) }}</span>
                                            </a>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
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
    @endif
</div>