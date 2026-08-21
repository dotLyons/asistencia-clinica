<?php

use App\Models\BillingPeriod;
use App\Models\Invoice;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to the login page when visiting billing periods', function () {
    $this->get(route('billing-periods'))
        ->assertRedirect(route('login'));
});

test('providers can visit the billing periods page but others cannot', function () {
    $provider = User::factory()->create();
    $provider->assignRole(Role::findOrCreate('prestador'));

    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));

    $this->actingAs($provider)
        ->get(route('billing-periods'))
        ->assertOk()
        ->assertSeeLivewire('prestador.billing-periods');

    $this->actingAs($employee)
        ->get(route('billing-periods'))
        ->assertStatus(403);
});

test('providers can create a billing period successfully', function () {
    $provider1 = User::factory()->create(['name' => 'Provider 1']);
    $provider1->assignRole(Role::findOrCreate('prestador'));

    $provider2 = User::factory()->create(['name' => 'Provider 2']);
    $provider2->assignRole(Role::findOrCreate('prestador'));

    Livewire::actingAs($provider1)
        ->test('prestador.billing-periods')
        ->set('start_date', '2026-09-01')
        ->set('end_date', '2026-09-30')
        ->set('max_amount', '500000.00')
        ->set('selectedPrestadores', [$provider2->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('start_date', '')
        ->assertSet('end_date', '')
        ->assertSet('max_amount', '')
        ->assertSet('selectedPrestadores', []);

    $this->assertDatabaseHas('billing_periods', [
        'creator_id' => $provider1->id,
        'start_date' => '2026-09-01 00:00:00',
        'end_date' => '2026-09-30 00:00:00',
        'max_amount' => 500000.00,
        'status' => 'pending_approval',
    ]);

    $period = BillingPeriod::first();
    // Check that both provider1 (creator) and provider2 are linked
    expect($period->users)->toHaveCount(2);
    expect($period->users->pluck('id'))->toContain($provider1->id, $provider2->id);
});

test('director can approve a billing period and accept cancellation', function () {
    $director = User::factory()->create();
    $director->assignRole(Role::findOrCreate('director'));

    $provider = User::factory()->create();
    $provider->assignRole(Role::findOrCreate('prestador'));

    $period = BillingPeriod::create([
        'creator_id' => $provider->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'max_amount' => 500000.00,
        'status' => 'pending_approval',
    ]);

    // 1. Approve Period
    Livewire::actingAs($director)
        ->test('director.director-dashboard')
        ->call('approvePeriod', $period->id)
        ->assertHasNoErrors();

    expect($period->fresh()->status)->toBe('approved');

    // 2. Request cancellation by provider
    Livewire::actingAs($provider)
        ->test('prestador.billing-periods')
        ->call('requestCancellation', $period->id)
        ->assertHasNoErrors();

    expect($period->fresh()->status)->toBe('pending_cancellation');

    // 3. Director approves cancellation
    Livewire::actingAs($director)
        ->test('director.director-dashboard')
        ->call('approveCancellation', $period->id)
        ->assertHasNoErrors();

    expect($period->fresh()->status)->toBe('cancelled');
});

test('invoicing amount constraints and validation rules work', function () {
    Storage::fake('public');

    $provider = User::factory()->create();
    $provider->assignRole(Role::findOrCreate('prestador'));

    $section = Section::create(['name' => 'Consultorio', 'uuid' => (string) Str::uuid()]);

    $period = BillingPeriod::create([
        'creator_id' => $provider->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'max_amount' => 1000.00,
        'status' => 'approved',
    ]);
    $period->users()->sync([$provider->id]);

    $pdf = UploadedFile::fake()->create('invoice.pdf', 500, 'application/pdf');

    // 1. Test uploading invoice with amount exceeding remaining allowance (limit: 1000.00)
    Livewire::actingAs($provider)
        ->test('prestador.invoices')
        ->set('billing_period_id', $period->id)
        ->set('month', 8)
        ->set('year', 2026)
        ->set('section_id', $section->id)
        ->set('invoice_number', 'INV-001')
        ->set('issue_date', '2026-08-15')
        ->set('amount', '1001.00') // Over limit
        ->set('pdf', $pdf)
        ->call('save')
        ->assertHasErrors(['amount']);

    // 2. Upload valid invoice of 800.00 (within limit)
    Livewire::actingAs($provider)
        ->test('prestador.invoices')
        ->set('billing_period_id', $period->id)
        ->set('month', 8)
        ->set('year', 2026)
        ->set('section_id', $section->id)
        ->set('invoice_number', 'INV-001')
        ->set('issue_date', '2026-08-15')
        ->set('amount', '800.00')
        ->set('pdf', $pdf)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('invoices', [
        'user_id' => $provider->id,
        'billing_period_id' => $period->id,
        'amount' => 800.00,
    ]);

    // 3. Try to upload another invoice of 300.00 (exceeds remaining 200.00)
    Livewire::actingAs($provider)
        ->test('prestador.invoices')
        ->set('billing_period_id', $period->id)
        ->set('month', 8)
        ->set('year', 2026)
        ->set('section_id', $section->id)
        ->set('invoice_number', 'INV-002')
        ->set('issue_date', '2026-08-16')
        ->set('amount', '300.00') // Remaining is 200
        ->set('pdf', $pdf)
        ->call('save')
        ->assertHasErrors(['amount']);
});
