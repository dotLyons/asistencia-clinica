<?php

use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegistrationPinController;
use App\Livewire\Admin\Sections;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('register/pin', RegistrationPinController::class)
    ->middleware('guest')
    ->name('register.pin');

Route::get('storage-link', function () {
    try {
        Artisan::call('storage:link');

        return response()->json([
            'status' => 'success',
            'message' => 'Storage link created successfully.',
            'output' => Artisan::output(),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('attendance/scan/{section:uuid}', [AttendanceScanController::class, 'show'])->name('attendance.scan');
    Route::post('attendance/scan/{section:uuid}', [AttendanceScanController::class, 'store'])->name('attendance.scan.store');

    Route::get('admin/sections', Sections::class)->name('admin.sections');
    Route::view('invoices', 'invoices')->name('invoices');
});

require __DIR__.'/settings.php';
