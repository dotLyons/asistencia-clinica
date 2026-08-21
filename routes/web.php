<?php

use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegistrationPinController;
use App\Livewire\Admin\Sections;
use App\Models\BillingPeriod;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

Route::view('/', 'welcome')->name('home');

Route::post('register/pin', RegistrationPinController::class)
    ->middleware('guest')
    ->name('register.pin');

Route::get('storage-link', function () {
    return response()->json([
        'status' => 'info',
        'message' => 'Symlink creation is disabled by Hostinger PHP settings. However, you do not need it anymore since files are securely streamed directly via Laravel web routes.',
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('attendance/scan/{section:uuid}', [AttendanceScanController::class, 'show'])->name('attendance.scan');
    Route::post('attendance/scan/{section:uuid}', [AttendanceScanController::class, 'store'])->name('attendance.scan.store');

    Route::get('admin/sections', Sections::class)->name('admin.sections');
    Route::view('invoices', 'invoices')->name('invoices');

    // Billing Periods & Prestador Invoices
    Route::view('billing-periods', 'billing-periods')->name('billing-periods');
    Route::view('prestador/invoices', 'prestador-invoices')->name('prestador.invoices');

    // Director merged invoice download
    Route::get('director/download-invoices/{billingPeriod}/{user}', function (BillingPeriod $billingPeriod, User $user) {
        abort_unless(
            auth()->check() && (
                auth()->user()->hasRole('director') ||
                auth()->user()->hasRole('administrador')
            ),
            403
        );

        $invoices = Invoice::where('billing_period_id', $billingPeriod->id)
            ->where('user_id', $user->id)
            ->get();

        if ($invoices->isEmpty()) {
            abort(404, __('No se encontraron facturas para este prestador en el periodo seleccionado.'));
        }

        $pdf = new Fpdi;
        $filesMerged = 0;

        foreach ($invoices as $invoice) {
            $filePath = Storage::disk('public')->path($invoice->pdf_path);
            if (file_exists($filePath)) {
                try {
                    $pageCount = $pdf->setSourceFile($filePath);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($templateId);
                        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $pdf->useTemplate($templateId);
                    }
                    $filesMerged++;
                } catch (Exception $e) {
                    Log::warning("Failed to import PDF page for invoice ID {$invoice->id}: ".$e->getMessage());

                    continue;
                }
            }
        }

        if ($filesMerged === 0) {
            abort(404, __('No se pudieron procesar las facturas.'));
        }

        $tempFilePath = tempnam(sys_get_temp_dir(), 'merged_director_').'.pdf';
        $pdf->Output('F', $tempFilePath);

        $providerName = Str::slug($user->name) ?: 'prestador';
        $filename = "facturas-{$providerName}-periodo-{$billingPeriod->id}.pdf";

        return response()->download($tempFilePath, $filename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    })->name('director.download-invoices');

    // Secure direct streaming of invoice PDFs
    Route::get('storage/invoices/{invoice}', function (Invoice $invoice) {
        abort_unless(
            auth()->check() && (
                auth()->user()->hasRole('administrador') ||
                auth()->id() === $invoice->user_id
            ),
            403
        );

        $path = $invoice->pdf_path;

        if (! Storage::disk('public')->exists($path)) {
            abort(404, __('Archivo no encontrado.'));
        }

        return Storage::disk('public')->response($path);
    })->name('invoices.download');
});

require __DIR__.'/settings.php';
