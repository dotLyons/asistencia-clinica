<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Section;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $tab = 'employees'; // 'employees' or 'history'
    public ?int $selectedEmployeeId = null;
    public ?int $employeeSectionId = null;
    public ?int $selectedSectionId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => 'employees'],
        'selectedEmployeeId' => ['except' => null],
        'selectedSectionId' => ['except' => null],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage('employees-page');
        $this->resetPage('history-page');
        $this->resetPage('section-employees-page');
    }

    public function updatingSelectedSectionId(): void
    {
        $this->resetPage('section-employees-page');
        $this->resetPage('section-history-page');
    }

    public function selectEmployee(int $id): void
    {
        $this->selectedEmployeeId = $id;
        $employee = User::find($id);
        $this->employeeSectionId = $employee?->section_id;
        $this->resetPage('employee-attendances-page');
    }

    public function updatedEmployeeSectionId($value): void
    {
        if ($this->selectedEmployeeId) {
            $employee = User::find($this->selectedEmployeeId);
            if ($employee) {
                $employee->section_id = $value ?: null;
                $employee->save();
                \Flux\Flux::toast(variant: 'success', text: __('Sección asignada correctamente.'));
            }
        }
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

    private function getTimelineSegmentsForDate(User $employee, \Carbon\CarbonInterface $date): array
    {
        $attendances = $employee->attendances()
            ->whereDate('occurred_at', $date)
            ->orderBy('occurred_at', 'asc')
            ->get();

        $segments = [];
        $totalHours = 0.0;
        $lastEntry = null;

        foreach ($attendances as $att) {
            if ($att->type === 'entrada') {
                $lastEntry = $att->occurred_at;
            } elseif ($att->type === 'salida' && $lastEntry) {
                $startSec = $lastEntry->diffInSeconds($date->copy()->startOfDay());
                $durationSec = $att->occurred_at->diffInSeconds($lastEntry);

                $segments[] = [
                    'start_pct' => ($startSec / 86400) * 100,
                    'duration_pct' => ($durationSec / 86400) * 100,
                    'start_time' => $lastEntry->format('H:i'),
                    'end_time' => $att->occurred_at->format('H:i'),
                    'duration_hrs' => round($durationSec / 3600, 2),
                ];
                $totalHours += $durationSec / 3600;
                $lastEntry = null;
            }
        }

        if ($lastEntry) {
            $startSec = $lastEntry->diffInSeconds($date->copy()->startOfDay());
            $endOfSegment = $date->isToday() ? now() : $date->copy()->endOfDay();
            $durationSec = $endOfSegment->diffInSeconds($lastEntry);

            $segments[] = [
                'start_pct' => ($startSec / 86400) * 100,
                'duration_pct' => ($durationSec / 86400) * 100,
                'start_time' => $lastEntry->format('H:i'),
                'end_time' => $date->isToday() ? __('Activo') : $endOfSegment->format('H:i'),
                'duration_hrs' => round($durationSec / 3600, 2),
                'is_active' => $date->isToday(),
            ];
            $totalHours += $durationSec / 3600;
        }

        return [
            'segments' => $segments,
            'total_hours' => round($totalHours, 1),
        ];
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
            $timelineSegments = [];
            $totalHoursToday = 0.0;
            $weeklyTimelines = [];

            if ($selectedEmployee) {
                $lastAttendance = $selectedEmployee->attendances()
                    ->latest('occurred_at')
                    ->latest('id')
                    ->first();
                $isActive = $lastAttendance && $lastAttendance->type === 'entrada';

                $hoursThisWeek = $this->getHoursWorked($selectedEmployee, now()->startOfWeek(), now()->endOfWeek());
                $hoursThisMonth = $this->getHoursWorked($selectedEmployee, now()->startOfMonth(), now()->endOfMonth());

                $employeeAttendances = $selectedEmployee->attendances()
                    ->with('section')
                    ->latest('occurred_at')
                    ->latest('id')
                    ->paginate(10, pageName: 'employee-attendances-page');

                // Today
                $todayData = $this->getTimelineSegmentsForDate($selectedEmployee, today());
                $timelineSegments = $todayData['segments'];
                $totalHoursToday = $todayData['total_hours'];

                // Weekly (Lunes a Domingo)
                $startOfWeek = now()->startOfWeek();
                for ($i = 0; $i < 7; $i++) {
                    $date = $startOfWeek->copy()->addDays($i);
                    $dayData = $this->getTimelineSegmentsForDate($selectedEmployee, $date);
                    $weeklyTimelines[] = [
                        'date_string' => $date->translatedFormat('l d/m'),
                        'is_today' => $date->isToday(),
                        'is_future' => $date->isAfter(today()),
                        'segments' => $dayData['segments'],
                        'total_hours' => $dayData['total_hours'],
                        'date_val' => $date,
                    ];
                }
            }

            return [
                'selectedEmployee' => $selectedEmployee,
                'isActive' => $isActive,
                'lastAttendance' => $lastAttendance,
                'hoursThisWeek' => $hoursThisWeek,
                'hoursThisMonth' => $hoursThisMonth,
                'employeeAttendances' => $employeeAttendances,
                'timelineSegments' => $timelineSegments,
                'totalHoursToday' => $totalHoursToday,
                'weeklyTimelines' => $weeklyTimelines,
                'allSections' => Section::orderBy('name')->get(),
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
        $employees = null;
        $history = null;
        $sectionEmployees = null;
        $sectionHistory = null;

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
        } elseif ($this->tab === 'history') {
            $history = Attendance::with(['user', 'section'])
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
        } elseif ($this->tab === 'section_history') {
            if ($this->selectedSectionId) {
                $sectionEmployees = User::whereDoesntHave('roles', function (Builder $query) {
                    $query->where('name', 'administrador');
                })
                ->where('section_id', $this->selectedSectionId)
                ->when($this->search, function (Builder $query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
                })
                ->withCount('attendances')
                ->orderBy('name')
                ->paginate(10, pageName: 'section-employees-page');

                $sectionHistory = Attendance::with('user')
                    ->where('section_id', $this->selectedSectionId)
                    ->latest('occurred_at')
                    ->latest('id')
                    ->paginate(15, pageName: 'section-history-page');
            }
        }

        return [
            'totalEmployees' => $totalEmployees,
            'activeToday' => $activeToday,
            'movementsToday' => $movementsToday,
            'employees' => $employees,
            'history' => $history,
            'sectionEmployees' => $sectionEmployees,
            'sectionHistory' => $sectionHistory,
            'selectedEmployee' => null,
            'allSections' => Section::orderBy('name')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    @if ($selectedEmployeeId && $selectedEmployee)
        <!-- Profile View -->
        <div class="flex flex-col gap-6">
            <!-- Header / Navigation -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                    <div class="flex items-center gap-4">
                        <flux:avatar
                            :name="$selectedEmployee->name"
                            :initials="$selectedEmployee->initials()"
                            class="bg-[#0f2f5f] text-white size-14 shadow-xs dark:bg-blue-600"
                        />
                        <div>
                            <h1 class="text-2xl font-bold text-slate-950 dark:text-slate-100">{{ $selectedEmployee->name }}</h1>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $selectedEmployee->email }}</p>
                        </div>
                    </div>
                    <div class="w-64 sm:border-s sm:border-slate-200 sm:ps-6 dark:sm:border-slate-800">
                        <flux:select wire:model.live="employeeSectionId" placeholder="{{ __('Sin sección asignada') }}" label="{{ __('Sección Asignada') }}">
                            @foreach ($allSections as $sec)
                                <flux:select.option value="{{ $sec->id }}">{{ $sec->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
                
                <flux:button icon="arrow-left" variant="filled" wire:click="$set('selectedEmployeeId', null)" class="self-start sm:self-auto cursor-pointer bg-[#0f2f5f] text-white hover:bg-[#173f7a] dark:bg-blue-600 dark:hover:bg-blue-500">
                    {{ __('Volver al listado') }}
                </flux:button>
            </div>

            <!-- Metric Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <!-- Card 1: Estado Actual -->
                <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="flex items-center gap-4">
                        @if ($isActive)
                            <div class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 shadow-2xs dark:bg-emerald-950/60 dark:text-emerald-400">
                                <flux:icon.check class="size-5" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Estado Actual') }}</p>
                                <span class="inline-flex rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800 px-2.5 py-0.5 text-xs font-semibold mt-1">
                                    {{ __('Activo') }}
                                </span>
                                @if ($lastAttendance)
                                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-1">
                                        {{ __('Entrada: :time', ['time' => $lastAttendance->occurred_at->format('H:i')]) }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="flex size-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400 shadow-2xs dark:bg-slate-800 dark:text-slate-400">
                                <flux:icon.user class="size-5" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Estado Actual') }}</p>
                                <span class="inline-flex rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 px-2.5 py-0.5 text-xs font-semibold mt-1">
                                    {{ __('Inactivo') }}
                                </span>
                                @if ($lastAttendance)
                                    <p class="text-xs text-slate-400 dark:text-slate-400 mt-1">
                                        {{ __('Salida: :time', ['time' => $lastAttendance->occurred_at->format('H:i')]) }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Card 2: Horas Semanales -->
                <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="flex items-center gap-4">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-blue-50 text-[#0f2f5f] shadow-2xs dark:bg-blue-950/60 dark:text-blue-400">
                            <flux:icon.clock class="size-5" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Horas esta semana') }}</p>
                            <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">{{ $hoursThisWeek }} hrs</p>
                            <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">
                                {{ __('Lun a Dom') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Horas Mensuales -->
                <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="flex items-center gap-4">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600 shadow-2xs dark:bg-amber-950/60 dark:text-amber-400">
                            <flux:icon.clock class="size-5" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Horas este mes') }}</p>
                            <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">{{ $hoursThisMonth }} hrs</p>
                            <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">
                                {{ now()->translatedFormat('F Y') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Línea de Tiempo de Horas Trabajadas Hoy -->
            <div class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm space-y-4 dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-slate-100">{{ __('Línea de Tiempo de Presencia (Hoy)') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Visualización de los tramos trabajados durante las 24 horas del día de hoy.') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Total Hoy') }}</p>
                        <p class="text-xl font-bold text-[#0f2f5f] dark:text-blue-400">{{ $totalHoursToday }} hrs</p>
                    </div>
                </div>

                <!-- Barra de Línea de Tiempo -->
                <div class="relative h-6 w-full rounded-lg bg-slate-100/80 border border-slate-200 overflow-hidden dark:bg-slate-800/80 dark:border-slate-700">
                    <!-- Segmentos Trabajados -->
                    @foreach ($timelineSegments as $seg)
                        <div 
                            class="absolute top-0 bottom-0 @if(isset($seg['is_active'])) bg-gradient-to-r from-emerald-400 to-teal-500 animate-pulse @else bg-gradient-to-r from-[#0f2f5f] to-[#1e4f91] dark:from-blue-600 dark:to-blue-400 @endif rounded-xs cursor-help"
                            style="left: {{ $seg['start_pct'] }}%; width: {{ $seg['duration_pct'] }}%;"
                            title="{{ $seg['start_time'] }} - {{ $seg['end_time'] }} ({{ $seg['duration_hrs'] }} hrs)"
                        >
                        </div>
                    @endforeach

                    <!-- Indicador de Hora Actual -->
                    @php
                        $nowPct = (now()->diffInSeconds(today()) / 86400) * 100;
                    @endphp
                    @if ($nowPct >= 0 && $nowPct <= 100)
                        <div class="absolute top-0 bottom-0 w-0.5 bg-rose-500 z-10 animate-pulse" style="left: {{ $nowPct }}%;" title="{{ __('Hora actual: :time', ['time' => now()->format('H:i')]) }}">
                            <div class="absolute -top-1 -left-1 size-2 rounded-full bg-rose-500"></div>
                        </div>
                    @endif
                </div>

                <!-- Ejes de Tiempo / Horas de Referencia -->
                <div class="flex justify-between text-[10px] font-semibold text-slate-400 dark:text-slate-500 px-1 uppercase">
                    <span>00:00</span>
                    <span>04:00</span>
                    <span>08:00</span>
                    <span>12:00</span>
                    <span>16:00</span>
                    <span>20:00</span>
                    <span>24:00</span>
                </div>

                <!-- Detalle de Tramos -->
                @if (count($timelineSegments) > 0)
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 pt-2">
                        @foreach ($timelineSegments as $seg)
                            <div class="flex items-center gap-2.5 rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 text-xs dark:border-slate-800 dark:bg-slate-800/40">
                                <span @class([
                                    'size-2.5 rounded-full',
                                    'bg-emerald-500' => isset($seg['is_active']),
                                    'bg-[#0f2f5f] dark:bg-blue-400' => !isset($seg['is_active']),
                                ])></span>
                                <div class="flex-1">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $seg['start_time'] }} - {{ $seg['end_time'] }}</p>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Duración:') }} {{ $seg['duration_hrs'] }} hrs</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-xs text-slate-400 dark:text-slate-500 py-2">
                        {{ __('No hay registros de presencia para el día de hoy.') }}
                    </p>
                @endif
            </div>

            <!-- Línea de Tiempo Semanal (Lunes a Domingo) -->
            <div class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm space-y-6 dark:border-slate-800 dark:bg-slate-900/95">
                <div>
                    <h3 class="text-base font-semibold text-slate-950 dark:text-slate-100">{{ __('Líneas de Tiempo Semanales') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Desglose diario del tiempo trabajado durante la semana actual.') }}</p>
                </div>

                <div class="space-y-4">
                    @foreach ($weeklyTimelines as $day)
                        <div class="flex flex-col gap-1.5 sm:flex-row sm:items-center">
                            <!-- Día -->
                            <div class="w-32 text-xs font-semibold text-slate-700 dark:text-slate-300 capitalize">
                                {{ $day['date_string'] }}
                                @if ($day['is_today'])
                                    <span class="ml-1 inline-flex items-center rounded-md bg-emerald-50 px-1.5 py-0.5 text-[9px] font-medium text-emerald-700 ring-1 ring-emerald-600/10 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800">
                                        {{ __('Hoy') }}
                                    </span>
                                @endif
                            </div>

                            <!-- Barra de Tiempo Compuesta -->
                            <div class="relative h-4.5 flex-1 rounded-md bg-slate-100/80 border border-slate-200 overflow-hidden dark:bg-slate-800/80 dark:border-slate-700">
                                @if ($day['is_future'])
                                    <!-- Día Futuro (Línea discontinua) -->
                                    <div class="absolute inset-0 bg-[repeating-linear-gradient(45deg,#f1f5f9,#f1f5f9_10px,#f8fafc_10px,#f8fafc_20px)] dark:bg-[repeating-linear-gradient(45deg,#1e293b,#1e293b_10px,#0f172a_10px,#0f172a_20px)] opacity-50"></div>
                                @else
                                    <!-- Segmentos -->
                                    @foreach ($day['segments'] as $seg)
                                        <div 
                                            class="absolute top-0 bottom-0 @if(isset($seg['is_active'])) bg-gradient-to-r from-emerald-400 to-teal-500 animate-pulse @else bg-gradient-to-r from-[#0f2f5f] to-[#1e4f91] dark:from-blue-600 dark:to-blue-400 @endif rounded-xs cursor-help"
                                            style="left: {{ $seg['start_pct'] }}%; width: {{ $seg['duration_pct'] }}%;"
                                            title="{{ $seg['start_time'] }} - {{ $seg['end_time'] }} ({{ $seg['duration_hrs'] }} hrs)"
                                        >
                                        </div>
                                    @endforeach

                                    <!-- Marcador de Hora Actual si es hoy -->
                                    @if ($day['is_today'])
                                        @php
                                            $nowPct = (now()->diffInSeconds(today()) / 86400) * 100;
                                        @endphp
                                        @if ($nowPct >= 0 && $nowPct <= 100)
                                            <div class="absolute top-0 bottom-0 w-0.5 bg-rose-500 z-10 animate-pulse" style="left: {{ $nowPct }}%;" title="{{ __('Hora actual: :time', ['time' => now()->format('H:i')]) }}">
                                                <div class="absolute -top-0.5 -left-1 size-1.5 rounded-full bg-rose-500"></div>
                                            </div>
                                        @endif
                                    @endif
                                @endif
                            </div>

                            <!-- Total del Día -->
                            <div class="w-16 text-right text-xs font-bold text-slate-900 dark:text-slate-100">
                                @if ($day['is_future'])
                                    <span class="text-slate-400 dark:text-slate-500 font-normal">—</span>
                                @else
                                    {{ $day['total_hours'] }} hrs
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Ejes de Referencia Horaria -->
                <div class="flex justify-between text-[10px] font-semibold text-slate-400 dark:text-slate-500 px-1 uppercase sm:pl-32 sm:pr-16">
                    <span>00:00</span>
                    <span>04:00</span>
                    <span>08:00</span>
                    <span>12:00</span>
                    <span>16:00</span>
                    <span>20:00</span>
                    <span>24:00</span>
                </div>
            </div>

            <!-- Attendance History Table -->
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">{{ __('Historial de Fichajes') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Historial completo de entradas y salidas de este empleado.') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-6 py-3">{{ __('Tipo') }}</th>
                                <th class="px-6 py-3">{{ __('Sección') }}</th>
                                <th class="px-6 py-3">{{ __('Fecha') }}</th>
                                <th class="px-6 py-3">{{ __('Hora') }}</th>
                                <th class="px-6 py-3">{{ __('Ubicación') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($employeeAttendances as $attendance)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-6 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                            'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800' => $attendance->type === 'entrada',
                                            'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800' => $attendance->type === 'salida',
                                        ])>
                                            {{ str($attendance->type)->title() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-700 dark:text-slate-200 font-medium">{{ $attendance->section?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 text-slate-700 dark:text-slate-200 font-medium">{{ $attendance->occurred_at->format('Y-m-d') }}</td>
                                    <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ $attendance->occurred_at->format('H:i:s') }}</td>
                                    <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                        @if ($attendance->latitude && $attendance->longitude)
                                            <a href="https://www.google.com/maps/search/?api=1&query={{ $attendance->latitude }},{{ $attendance->longitude }}" target="_blank" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium hover:underline">
                                                <svg class="size-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>{{ round($attendance->latitude, 4) }}, {{ round($attendance->longitude, 4) }}</span>
                                            </a>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                        {{ __('Aún no hay registros de asistencia para este empleado.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($employeeAttendances && $employeeAttendances->hasPages())
                    <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-4">
                        {{ $employeeAttendances->links() }}
                    </div>
                @endif
            </section>
        </div>
    @else
        <!-- Header -->
        <header class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-[#1e4f91] dark:text-blue-400">{{ __('Panel de Administración') }}</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ __('Control Global de Asistencia') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('Visualiza todos los empleados, sus movimientos recientes y el historial completo de asistencia.') }}</p>
            </div>
        </header>

        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-blue-50 text-[#0f2f5f] shadow-2xs dark:bg-blue-950/60 dark:text-blue-400">
                        <flux:icon.users class="size-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Total Empleados') }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">{{ $totalEmployees }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 shadow-2xs dark:bg-emerald-950/60 dark:text-emerald-400">
                        <flux:icon.user class="size-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Activos Hoy') }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">{{ $activeToday }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white/95 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600 shadow-2xs dark:bg-amber-950/60 dark:text-amber-400">
                        <flux:icon.clock class="size-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Registros Hoy') }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">{{ $movementsToday }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Tabs -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between rounded-xl border border-slate-200 bg-white/95 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
            <div class="relative flex-1 max-w-md">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="{{ __('Buscar por nombre o correo...') }}" 
                    icon="magnifying-glass"
                />
            </div>

            <div class="flex border-b border-slate-200 dark:border-slate-800 self-start lg:self-auto">
                <button wire:click="$set('tab', 'employees')" @class([
                    'px-4 py-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer',
                    'border-[#0f2f5f] text-[#0f2f5f] dark:border-blue-400 dark:text-blue-400' => $tab === 'employees',
                    'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $tab !== 'employees'
                ])>
                    {{ __('Empleados') }}
                </button>
                <button wire:click="$set('tab', 'history')" @class([
                    'px-4 py-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer',
                    'border-[#0f2f5f] text-[#0f2f5f] dark:border-blue-400 dark:text-blue-400' => $tab === 'history',
                    'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $tab !== 'history'
                ])>
                    {{ __('Historial General') }}
                </button>
                <button wire:click="$set('tab', 'section_history')" @class([
                    'px-4 py-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer',
                    'border-[#0f2f5f] text-[#0f2f5f] dark:border-blue-400 dark:text-blue-400' => $tab === 'section_history',
                    'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $tab !== 'section_history'
                ])>
                    {{ __('Historial por Sección') }}
                </button>
            </div>
        </div>

        <!-- Content -->
        @if ($tab === 'employees')
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-6 py-3">{{ __('Empleado') }}</th>
                                <th class="px-6 py-3">{{ __('Correo') }}</th>
                                <th class="px-6 py-3">{{ __('Total Movimientos') }}</th>
                                <th class="px-6 py-3">{{ __('Último Registro') }}</th>
                                <th class="px-6 py-3 text-right">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($employees as $employee)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar
                                                :name="$employee->name"
                                                :initials="$employee->initials()"
                                                class="bg-[#0f2f5f] text-white size-9 shadow-2xs dark:bg-blue-600"
                                            />
                                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $employee->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $employee->email }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300 font-semibold">{{ $employee->attendances_count }}</td>
                                    <td class="px-6 py-4">
                                        @if ($last = $employee->attendances->first())
                                            <div class="flex items-center gap-2">
                                                <span @class([
                                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                                    'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800' => $last->type === 'entrada',
                                                    'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800' => $last->type === 'salida',
                                                ])>
                                                    {{ str($last->type)->title() }}
                                                </span>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $last->occurred_at->format('Y-m-d H:i') }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <flux:button size="sm" variant="filled" icon="eye" wire:click="selectEmployee({{ $employee->id }})" class="cursor-pointer bg-[#0f2f5f] text-white hover:bg-[#173f7a] dark:bg-blue-600 dark:hover:bg-blue-500">
                                            {{ __('Ver Historial') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                        {{ __('No se encontraron empleados.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($employees && $employees->hasPages())
                    <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-4">
                        {{ $employees->links() }}
                    </div>
                @endif
            </section>
        @elseif ($tab === 'history')
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-6 py-3">{{ __('Empleado') }}</th>
                                <th class="px-6 py-3">{{ __('Correo') }}</th>
                                <th class="px-6 py-3">{{ __('Tipo') }}</th>
                                <th class="px-6 py-3">{{ __('Sección') }}</th>
                                <th class="px-6 py-3">{{ __('Fecha') }}</th>
                                <th class="px-6 py-3">{{ __('Hora') }}</th>
                                <th class="px-6 py-3">{{ __('Ubicación') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($history as $record)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar
                                                :name="$record->user->name"
                                                :initials="$record->user->initials()"
                                                class="bg-[#0f2f5f] text-white size-8 shadow-2xs dark:bg-blue-600"
                                            />
                                            <span>{{ $record->user->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $record->user->email }}</td>
                                    <td class="px-6 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                            'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800' => $record->type === 'entrada',
                                            'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800' => $record->type === 'salida',
                                        ])>
                                            {{ str($record->type)->title() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-700 dark:text-slate-200 font-medium">{{ $record->section?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $record->occurred_at->format('Y-m-d') }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $record->occurred_at->format('H:i:s') }}</td>
                                    <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                        @if ($record->latitude && $record->longitude)
                                            <a href="https://www.google.com/maps/search/?api=1&query={{ $record->latitude }},{{ $record->longitude }}" target="_blank" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium hover:underline">
                                                <svg class="size-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>{{ round($record->latitude, 4) }}, {{ round($record->longitude, 4) }}</span>
                                            </a>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                        {{ __('No hay registros de asistencia.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($history && $history->hasPages())
                    <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-4">
                        {{ $history->links() }}
                    </div>
                @endif
            </section>
        @else
            <!-- Pestaña de Historial por Sección -->
            <div class="flex flex-col gap-6">
                <div class="rounded-xl border border-slate-200 bg-white/95 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="max-w-md">
                        <flux:select wire:model.live="selectedSectionId" placeholder="{{ __('Seleccionar sección...') }}" label="{{ __('Selecciona una sección') }}">
                            @foreach ($allSections as $sec)
                                <flux:select.option value="{{ $sec->id }}">{{ $sec->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                @if ($selectedSectionId && $selectedSectionId !== '')
                    @php
                        $activeSectionName = $allSections->firstWhere('id', $selectedSectionId)?->name ?? '';
                    @endphp
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Columna 1: Empleados de la Sección -->
                        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col h-fit dark:border-slate-800 dark:bg-slate-900">
                            <div class="border-b border-slate-200 px-6 py-4 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/60">
                                <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">{{ __('Empleados en :section', ['section' => $activeSectionName]) }}</h2>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Lista de empleados asignados a esta sección.') }}</p>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[380px] text-sm">
                                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-100 dark:bg-slate-800/60 dark:text-slate-400 dark:border-slate-800">
                                        <tr>
                                            <th class="px-6 py-3">{{ __('Empleado') }}</th>
                                            <th class="px-6 py-3 text-right">{{ __('Acciones') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @forelse ($sectionEmployees as $emp)
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <flux:avatar
                                                            :name="$emp->name"
                                                            :initials="$emp->initials()"
                                                            class="bg-[#0f2f5f] text-white size-8 shadow-2xs dark:bg-blue-600"
                                                        />
                                                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ $emp->name }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <flux:button size="xs" variant="filled" icon="eye" wire:click="selectEmployee({{ $emp->id }})" class="cursor-pointer bg-[#0f2f5f] text-white hover:bg-[#173f7a] dark:bg-blue-600 dark:hover:bg-blue-500">
                                                        {{ __('Ver Historial') }}
                                                    </flux:button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                                    {{ __('No hay empleados asignados a esta sección.') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if ($sectionEmployees && $sectionEmployees->hasPages())
                                <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-3">
                                    {{ $sectionEmployees->links() }}
                                </div>
                            @endif
                        </section>

                        <!-- Columna 2: Historial Reciente de la Sección -->
                        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col h-fit dark:border-slate-800 dark:bg-slate-900">
                            <div class="border-b border-slate-200 px-6 py-4 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/60">
                                <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">{{ __('Historial Reciente en :section', ['section' => $activeSectionName]) }}</h2>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Registros de entrada y salida en esta sección.') }}</p>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[380px] text-sm">
                                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-100 dark:bg-slate-800/60 dark:text-slate-400 dark:border-slate-800">
                                        <tr>
                                            <th class="px-6 py-3">{{ __('Empleado') }}</th>
                                            <th class="px-6 py-3">{{ __('Movimiento') }}</th>
                                            <th class="px-6 py-3">{{ __('Fecha/Hora') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @forelse ($sectionHistory as $rec)
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                                <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100">
                                                    {{ $rec->user->name }}
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span @class([
                                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                        'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800' => $rec->type === 'entrada',
                                                        'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800' => $rec->type === 'salida',
                                                    ])>
                                                        {{ str($rec->type)->title() }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                                    {{ $rec->occurred_at->format('Y-m-d H:i:s') }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                                    {{ __('No hay registros de asistencia en esta sección.') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if ($sectionHistory && $sectionHistory->hasPages())
                                <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-3">
                                    {{ $sectionHistory->links() }}
                                </div>
                            @endif
                        </section>
                    </div>
                @else
                    <div class="rounded-xl border border-slate-200 bg-white p-12 text-center text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                        <flux:icon.building-office class="size-12 mx-auto text-slate-300 dark:text-slate-600" />
                        <p class="mt-4 font-medium text-slate-900 dark:text-slate-100">{{ __('Selecciona una sección') }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Por favor, selecciona una de las secciones disponibles para visualizar sus empleados e historial.') }}</p>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>