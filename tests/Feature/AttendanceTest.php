<?php

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee scan alternates between entry and exit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('attendance.scan'))
        ->assertRedirect(route('dashboard', absolute: false));

    expect($user->attendances()->first())
        ->type->toBe('entrada');

    $this->actingAs($user)
        ->get(route('attendance.scan'))
        ->assertRedirect(route('dashboard', absolute: false));

    expect($user->attendances()->latest('id')->first())
        ->type->toBe('salida');
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

test('attendance qr page is public', function () {
    $this->get(route('attendance.qr'))
        ->assertOk()
        ->assertSee('QR de asistencia')
        ->assertSee(route('attendance.qr.download'));
});

test('attendance qr image is public', function () {
    $this->get(route('attendance.qr.image'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

test('attendance qr can be downloaded publicly', function () {
    $this->get(route('attendance.qr.download'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'attachment; filename="qr-asistencia.svg"');
});
