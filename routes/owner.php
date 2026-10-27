<?php

use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\SettingsController;
use App\Http\Controllers\Owner\StaffController;
use Illuminate\Support\Facades\Route;

// Panel pemilik dan staf. Prefix /owner dan nama owner.* (lihat bootstrap/app.php).
Route::middleware(['auth', 'role:owner,staff', 'tenant', 'tenant.active'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/settings', SettingsController::class)->middleware('can:settings.manage')->name('settings');

    Route::middleware('role:owner')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/invitations', [StaffController::class, 'invite'])->name('invite');
        Route::delete('/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])->name('invitations.revoke');
        Route::put('/{staff}', [StaffController::class, 'updatePermissions'])->name('update');
    });
});
