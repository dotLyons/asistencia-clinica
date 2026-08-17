<?php

use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegistrationPinController;
use App\Livewire\Admin\Sections;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('register/pin', RegistrationPinController::class)
    ->middleware('guest')
    ->name('register.pin');

Route::get('storage-link', function () {
    $target = storage_path('app/public');
    $link = public_path('storage');

    try {
        if (file_exists($link) || is_link($link)) {
            if (is_link($link)) {
                unlink($link);
            } elseif (is_dir($link)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The "public/storage" directory already exists as a physical directory. Please delete or rename it first so we can create a symlink.',
                ], 400);
            }
        }

        if (symlink($target, $link)) {
            return response()->json([
                'status' => 'success',
                'message' => "Symlink created successfully: [{$link}] connected to [{$target}].",
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create symlink.',
        ], 500);
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
