<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertSee('Registro de empleados');
});

test('new users can register', function () {
    $this->withSession(['registration_pin_verified' => true]);

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect($this->app['auth']->user()->hasRole('empleado'))->toBeTrue();
});

test('registration requires the pin', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertForbidden();
});

test('registration pin unlocks the registration form', function () {
    $response = $this->post(route('register.pin'), [
        'pin' => '1226500',
    ]);

    $response->assertRedirect(route('register', absolute: false));

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Crear una cuenta');
});
