<?php

use App\Http\Controllers\Owner\AuditLogController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\DataExportController;
use App\Http\Controllers\Owner\StaffController;
use Illuminate\Support\Facades\Route;

// Panel pemilik dan staf. Dimuat di bootstrap/app.php dengan middleware
// web, auth, tenant (SetCurrentTenant), dan tenant.active (EnsureTenantIsActive),
// prefix `app`, dan nama route `owner.`.

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/staf', [StaffController::class, 'index'])->name('staff.index');
Route::post('/staf/undangan', [StaffController::class, 'store'])->name('staff.invite');
Route::patch('/staf/{staff}', [StaffController::class, 'update'])->name('staff.update');
Route::delete('/staf/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

Route::get('/log-audit', [AuditLogController::class, 'index'])->name('audit.index');
Route::get('/ekspor', DataExportController::class)->middleware('throttle:6,1')->name('export');
