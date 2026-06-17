<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('admin users see the admin dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Panel de Administración')
        ->assertSeeLivewire('admin-dashboard');
});

test('employees see the employee dashboard and not the admin dashboard', function () {
    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Panel del empleado')
        ->assertDontSee('Panel de Administración')
        ->assertDontSeeLivewire('admin-dashboard');
});

test('admin dashboard can search employees', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    // Create roles for the employees so they are identified properly if needed,
    // though the code searches all non-admin users
    $employeeRole = Role::findOrCreate('empleado');
    $employee1 = User::factory()->create(['name' => 'John Doe']);
    $employee1->assignRole($employeeRole);
    $employee2 = User::factory()->create(['name' => 'Jane Smith']);
    $employee2->assignRole($employeeRole);

    Livewire::actingAs($admin)
        ->test('admin-dashboard')
        ->assertSee('John Doe')
        ->assertSee('Jane Smith')
        ->set('search', 'Jane')
        ->assertSee('Jane Smith')
        ->assertDontSee('John Doe');
});

test('admin dashboard can select employee and view their profile', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    $employee = User::factory()->create(['name' => 'John Doe']);
    $employee->assignRole(Role::findOrCreate('empleado'));

    Livewire::actingAs($admin)
        ->test('admin-dashboard')
        ->call('selectEmployee', $employee->id)
        ->assertSet('selectedEmployeeId', $employee->id)
        ->assertSee('John Doe')
        ->assertSee('Volver al listado')
        ->assertSee('Horas esta semana')
        ->assertSee('Horas este mes');
});
