<?php

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('employee scan page renders and post registers attendance with coordinates', function () {
    $user = User::factory()->create();

    // 1. GET page should render the intermediate geolocation view
    $this->actingAs($user)
        ->get(route('attendance.scan'))
        ->assertOk()
        ->assertViewIs('attendance.scan');

    // 2. POST should store the attendance record with coordinates
    $this->actingAs($user)
        ->post(route('attendance.scan.store'), [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $attendance = $user->attendances()->first();
    expect($attendance)->not->toBeNull();
    expect($attendance->type)->toBe('entrada');
    expect((float) $attendance->latitude)->toBe(40.7128);
    expect((float) $attendance->longitude)->toBe(-74.0060);

    // 3. Second POST should record a exit (salida)
    $this->actingAs($user)
        ->post(route('attendance.scan.store'), [
            'latitude' => 40.7130,
            'longitude' => -74.0065,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $lastAttendance = $user->attendances()->latest('id')->first();
    expect($lastAttendance->type)->toBe('salida');
    expect((float) $lastAttendance->latitude)->toBe(40.7130);
    expect((float) $lastAttendance->longitude)->toBe(-74.0065);
});

test('dashboard only shows the authenticated employee attendances', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Attendance::factory()->for($user)->create([
        'type' => 'entrada',
        'occurred_at' => now(),
    ]);

    Attendance::factory()->for($otherUser)->create([
        'type' => 'salida',
        'occurred_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Entrada')
        ->assertDontSee('Salida')
        ->assertDontSee('QR de asistencia');
});

test('attendance qr page is restricted to administrators', function () {
    // 1. Guest access should redirect to login
    $this->get(route('attendance.qr'))
        ->assertRedirect(route('login'));

    // 2. Employee access should return 403
    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));
    $this->actingAs($employee)
        ->get(route('attendance.qr'))
        ->assertForbidden();

    // 3. Admin access should succeed
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));
    $this->actingAs($admin)
        ->get(route('attendance.qr'))
        ->assertOk()
        ->assertSee('QR público para empleados')
        ->assertSee(route('attendance.qr.download'));
});

test('attendance qr image is restricted to administrators', function () {
    // 1. Guest access should redirect to login
    $this->get(route('attendance.qr.image'))
        ->assertRedirect(route('login'));

    // 2. Employee access should return 403
    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));
    $this->actingAs($employee)
        ->get(route('attendance.qr.image'))
        ->assertForbidden();

    // 3. Admin access should succeed
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));
    $this->actingAs($admin)
        ->get(route('attendance.qr.image'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

test('attendance qr download is restricted to administrators', function () {
    // 1. Guest access should redirect to login
    $this->get(route('attendance.qr.download'))
        ->assertRedirect(route('login'));

    // 2. Employee access should return 403
    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));
    $this->actingAs($employee)
        ->get(route('attendance.qr.download'))
        ->assertForbidden();

    // 3. Admin access should succeed
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));
    $this->actingAs($admin)
        ->get(route('attendance.qr.download'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'attachment; filename="qr-asistencia.svg"');
});
