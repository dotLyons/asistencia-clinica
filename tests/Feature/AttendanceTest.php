<?php

use App\Livewire\Admin\Sections;
use App\Models\Attendance;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('employee scan page renders and post registers attendance with coordinates for their assigned section', function () {
    $user = User::factory()->create();
    $section = Section::factory()->create();

    // Assign the section to the user
    $user->section_id = $section->id;
    $user->save();

    // 1. GET page should render the intermediate geolocation view for the section
    $this->actingAs($user)
        ->get(route('attendance.scan', $section->uuid))
        ->assertOk()
        ->assertViewIs('attendance.scan')
        ->assertSee($section->name);

    // 2. POST should store the attendance record with coordinates and correct section_id
    $this->actingAs($user)
        ->post(route('attendance.scan.store', $section->uuid), [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $attendance = $user->attendances()->first();
    expect($attendance)->not->toBeNull();
    expect($attendance->type)->toBe('entrada');
    expect((float) $attendance->latitude)->toBe(40.7128);
    expect((float) $attendance->longitude)->toBe(-74.0060);
    expect($attendance->section_id)->toBe($section->id);

    // 3. Second POST should record an exit (salida)
    $this->actingAs($user)
        ->post(route('attendance.scan.store', $section->uuid), [
            'latitude' => 40.7130,
            'longitude' => -74.0065,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $lastAttendance = $user->attendances()->latest('id')->first();
    expect($lastAttendance->type)->toBe('salida');
    expect((float) $lastAttendance->latitude)->toBe(40.7130);
    expect((float) $lastAttendance->longitude)->toBe(-74.0065);
    expect($lastAttendance->section_id)->toBe($section->id);
});

test('employee cannot register attendance if they have no section assigned', function () {
    $user = User::factory()->create();
    $section = Section::factory()->create();

    // Ensure user has NO section assigned (it's null by default)
    expect($user->section_id)->toBeNull();

    // 1. GET page should render the error warning
    $this->actingAs($user)
        ->get(route('attendance.scan', $section->uuid))
        ->assertOk()
        ->assertSee('No tienes una sección de asistencia asignada');

    // 2. POST should be rejected with 403 Forbidden
    $this->actingAs($user)
        ->post(route('attendance.scan.store', $section->uuid), [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ])
        ->assertForbidden();
});

test('employee cannot register attendance for a section that is not assigned to them', function () {
    $user = User::factory()->create();
    $assignedSection = Section::factory()->create(['name' => 'Recepción']);
    $scannedSection = Section::factory()->create(['name' => 'Quirófano']);

    // Assign the Recepción section to the user
    $user->section_id = $assignedSection->id;
    $user->save();

    // 1. GET page for Quirófano should render the mismatch warning
    $this->actingAs($user)
        ->get(route('attendance.scan', $scannedSection->uuid))
        ->assertOk()
        ->assertSee('no coincide con tu sección asignada');

    // 2. POST to Quirófano should be rejected with 403 Forbidden
    $this->actingAs($user)
        ->post(route('attendance.scan.store', $scannedSection->uuid), [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ])
        ->assertForbidden();
});

test('dashboard only shows the authenticated employee attendances', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $section = Section::factory()->create();

    Attendance::factory()->for($user)->for($section)->create([
        'type' => 'entrada',
        'occurred_at' => now(),
    ]);

    Attendance::factory()->for($otherUser)->for($section)->create([
        'type' => 'salida',
        'occurred_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Entrada')
        ->assertSee($section->name)
        ->assertDontSee('Salida')
        ->assertDontSee('QR de asistencia');
});

test('sections page is restricted to administrators', function () {
    // 1. Guest access should redirect to login
    $this->get(route('admin.sections'))
        ->assertRedirect(route('login'));

    // 2. Employee access should return 403
    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));
    $this->actingAs($employee)
        ->get(route('admin.sections'))
        ->assertForbidden();

    // 3. Admin access should succeed
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));
    $this->actingAs($admin)
        ->get(route('admin.sections'))
        ->assertOk()
        ->assertSee('Gestión de Secciones');
});

test('admin can manage sections via livewire component', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    // 1. Test creation
    Livewire::actingAs($admin)
        ->test(Sections::class)
        ->set('name', 'Recepción Especial')
        ->call('createSection')
        ->assertHasNoErrors()
        ->assertSet('name', '');

    $this->assertDatabaseHas('sections', [
        'name' => 'Recepción Especial',
    ]);

    $section = Section::where('name', 'Recepción Especial')->first();

    // 2. Test search
    Section::factory()->create(['name' => 'Laboratorio']);
    Livewire::actingAs($admin)
        ->test(Sections::class)
        ->set('search', 'Laboratorio')
        ->assertSee('Laboratorio')
        ->assertDontSee('Recepción Especial');

    // 3. Test QR download
    $response = Livewire::actingAs($admin)
        ->test(Sections::class)
        ->call('downloadQr', $section->id);
        
    $response->assertFileDownloaded("qr-recepcion-especial.jpg");

    // 4. Test delete
    Livewire::actingAs($admin)
        ->test(Sections::class)
        ->call('deleteSection', $section->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('sections', [
        'id' => $section->id,
    ]);
});

test('admin can assign a section to an employee via admin-dashboard component', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    $employee = User::factory()->create();
    $section = Section::factory()->create(['name' => 'Consultas']);

    // Test assigning the section using the admin-dashboard component
    Livewire::actingAs($admin)
        ->test('admin-dashboard')
        ->call('selectEmployee', $employee->id)
        ->assertSet('selectedEmployeeId', $employee->id)
        ->assertSet('employeeSectionId', null)
        ->set('employeeSectionId', $section->id)
        ->assertHasNoErrors();

    // Verify the change in database
    $employee->refresh();
    expect($employee->section_id)->toBe($section->id);
});

test('admin dashboard can view employees and history filtered by section', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    $sectionA = Section::factory()->create(['name' => 'Sección Alfa']);
    $sectionB = Section::factory()->create(['name' => 'Sección Beta']);

    $employeeA = User::factory()->create(['name' => 'Alice']);
    $employeeA->section_id = $sectionA->id;
    $employeeA->save();

    $employeeB = User::factory()->create(['name' => 'Bob']);
    $employeeB->section_id = $sectionB->id;
    $employeeB->save();

    // Create an attendance record for Alice in Section Alfa
    $attendanceA = Attendance::factory()->create([
        'user_id' => $employeeA->id,
        'section_id' => $sectionA->id,
        'type' => 'entrada',
        'occurred_at' => now(),
    ]);

    // Test dashboard behavior under section_history tab
    Livewire::actingAs($admin)
        ->test('admin-dashboard')
        ->set('tab', 'section_history')
        ->assertSee('Selecciona una sección')
        ->set('selectedSectionId', $sectionA->id)
        ->assertSee('Alice')
        ->assertDontSee('Bob')
        ->assertSee('Sección Alfa')
        ->assertSee('Entrada');
});

test('admin dashboard computes and displays daily and weekly worked hours timeline segments', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    $employee = User::factory()->create();
    $section = Section::factory()->create();

    // Create entrance at 08:00 today and exit at 12:00 today
    $entry = Attendance::factory()->create([
        'user_id' => $employee->id,
        'section_id' => $section->id,
        'type' => 'entrada',
        'occurred_at' => today()->setHour(8)->setMinute(0),
    ]);

    $exit = Attendance::factory()->create([
        'user_id' => $employee->id,
        'section_id' => $section->id,
        'type' => 'salida',
        'occurred_at' => today()->setHour(12)->setMinute(0),
    ]);

    Livewire::actingAs($admin)
        ->test('admin-dashboard')
        ->call('selectEmployee', $employee->id)
        ->assertSee('Línea de Tiempo de Presencia (Hoy)')
        ->assertSee('Líneas de Tiempo Semanales')
        ->assertSee('08:00 - 12:00')
        ->assertSee('4 hrs');
});
