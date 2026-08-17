<?php

use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegistrationPinController;
use App\Livewire\Admin\Sections;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
