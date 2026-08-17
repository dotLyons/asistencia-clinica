<?php

use App\Models\Invoice;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to the login page when visiting invoices', function () {
    $response = $this->get(route('invoices'));
    $response->assertRedirect(route('login'));
});

test('employees can visit the invoices page', function () {
    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));

    $this->actingAs($employee)
        ->get(route('invoices'))
        ->assertOk()
        ->assertSee('Facturación')
        ->assertSeeLivewire('employee.invoices');
});

test('admins cannot visit the invoices page', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    $this->actingAs($admin)
        ->get(route('invoices'))
        ->assertStatus(403);
});

test('employee.invoices component validation rules work', function () {
    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));

    Livewire::actingAs($employee)
        ->test('employee.invoices')
        ->set('month', '')
        ->set('year', '')
        ->set('section_id', '')
        ->set('invoice_number', '')
        ->set('issue_date', '')
        ->set('amount', '')
        ->set('pdf', null)
        ->call('save')
        ->assertHasErrors([
            'month' => 'required',
            'year' => 'required',
            'section_id' => 'required',
            'invoice_number' => 'required',
            'issue_date' => 'required',
            'amount' => 'required',
            'pdf' => 'required',
        ]);
});

test('employee can upload a valid invoice successfully', function () {
    Storage::fake('public');

    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));

    $section = Section::create([
        'name' => 'Pediatría',
        'uuid' => (string) Str::uuid(),
    ]);

    $pdf = UploadedFile::fake()->create('invoice.pdf', 500, 'application/pdf');

    Livewire::actingAs($employee)
        ->test('employee.invoices')
        ->set('month', 8)
        ->set('year', 2026)
        ->set('section_id', $section->id)
        ->set('invoice_number', 'FC-0001-00004567')
        ->set('issue_date', '2026-08-15')
        ->set('amount', '150000.50')
        ->set('pdf', $pdf)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('invoice_number', '')
        ->assertSet('amount', '')
        ->assertSet('pdf', null);

    // Verify invoice is saved in the database
    $this->assertDatabaseHas('invoices', [
        'user_id' => $employee->id,
        'month' => 8,
        'year' => 2026,
        'section_id' => $section->id,
        'invoice_number' => 'FC-0001-00004567',
        'issue_date' => '2026-08-15 00:00:00',
        'amount' => 150000.50,
    ]);

    // Verify PDF is stored in the filesystem
    $invoice = Invoice::first();
    $this->assertNotNull($invoice->pdf_path);
    Storage::disk('public')->assertExists($invoice->pdf_path);
});

test('public route storage-link executes successfully', function () {
    $response = $this->get('/storage-link');
    $response->assertOk()
        ->assertJson([
            'status' => 'info',
        ]);
});

test('authenticated users can securely view their invoices and admins can view any invoice', function () {
    Storage::fake('public');

    $employee1 = User::factory()->create();
    $employee1->assignRole(Role::findOrCreate('empleado'));

    $employee2 = User::factory()->create();
    $employee2->assignRole(Role::findOrCreate('empleado'));

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    $section = Section::create(['name' => 'Sec1', 'uuid' => (string) Str::uuid()]);

    // Create invoice for employee 1
    $invoice = Invoice::create([
        'user_id' => $employee1->id,
        'month' => 8,
        'year' => 2026,
        'section_id' => $section->id,
        'invoice_number' => 'FC-001',
        'issue_date' => '2026-08-15',
        'amount' => 1000,
        'pdf_path' => 'invoices/test.pdf',
    ]);

    Storage::disk('public')->put('invoices/test.pdf', 'dummy pdf content');

    // Guest cannot download
    $this->get(route('invoices.download', $invoice))
        ->assertRedirect(route('login'));

    // Employee 1 can download their own invoice
    $this->actingAs($employee1)
        ->get(route('invoices.download', $invoice))
        ->assertOk();

    // Employee 2 CANNOT download Employee 1's invoice
    $this->actingAs($employee2)
        ->get(route('invoices.download', $invoice))
        ->assertStatus(403);

    // Admin can download any invoice
    $this->actingAs($admin)
        ->get(route('invoices.download', $invoice))
        ->assertOk();
});

test('admin can see employee invoices and download merged history', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('administrador'));

    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('empleado'));

    $section = Section::create([
        'name' => 'Cardiología',
        'uuid' => (string) Str::uuid(),
    ]);

    // Create a real valid PDF using Fpdi
    $fpdf = new Fpdi;
    $fpdf->AddPage();
    $fpdf->SetFont('Arial', 'B', 12);
    $fpdf->Cell(40, 10, 'Test Invoice Content');
    $tempPdfPath = tempnam(sys_get_temp_dir(), 'test_invoice_').'.pdf';
    $fpdf->Output('F', $tempPdfPath);

    // Save the file to public fake storage
    $storedPath = 'invoices/test-invoice-1.pdf';
    Storage::disk('public')->put($storedPath, file_get_contents($tempPdfPath));
    @unlink($tempPdfPath);

    // Create invoice record linked to this file
    $invoice = Invoice::create([
        'user_id' => $employee->id,
        'month' => 8,
        'year' => 2026,
        'section_id' => $section->id,
        'invoice_number' => 'INV-001',
        'issue_date' => '2026-08-15',
        'amount' => 1000.00,
        'pdf_path' => $storedPath,
    ]);

    // 1. Check if admin can see employee invoices list on dashboard
    Livewire::actingAs($admin)
        ->test('admin-dashboard')
        ->call('selectEmployee', $employee->id)
        ->assertSee('Historial de Facturaciones')
        ->assertSee('INV-001')
        ->assertSee('$1.000,00');

    // 2. Try to download merged history for an empty month/year
    Livewire::actingAs($admin)
        ->test('admin-dashboard')
        ->call('selectEmployee', $employee->id)
        ->set('history_month', 7) // July (empty)
        ->set('history_year', 2026)
        ->call('downloadMergedHistory')
        ->assertHasErrors(['history_month']);

    // 3. Download merged history for August 2026 (should succeed)
    $response = Livewire::actingAs($admin)
        ->test('admin-dashboard')
        ->call('selectEmployee', $employee->id)
        ->set('history_month', 8) // August (has 1 invoice)
        ->set('history_year', 2026)
        ->call('downloadMergedHistory');

    $response->assertFileDownloaded();
});
