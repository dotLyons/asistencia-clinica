<?php

use App\Http\Controllers\AttendanceQrCodeController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RegistrationPinController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('register/pin', RegistrationPinController::class)
    ->middleware('guest')
    ->name('register.pin');

Route::get('attendance/qr', [AttendanceQrCodeController::class, 'show'])->name('attendance.qr');
Route::get('attendance/qr.svg', [AttendanceQrCodeController::class, 'image'])->name('attendance.qr.image');
Route::get('attendance/qr/download', [AttendanceQrCodeController::class, 'download'])->name('attendance.qr.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('attendance/scan', AttendanceScanController::class)->name('attendance.scan');
});

require __DIR__.'/settings.php';
