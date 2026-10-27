<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SubscriptionInvoiceController;
use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

// Panel Super Admin. Prefix /admin dan nama admin.* (lihat bootstrap/app.php).
// Hanya role super_admin; konteks tenant lintas tenant (SetCurrentTenant), jadi setiap controller juga memeriksa
// Gate platform.manage dan membatasi query dengan jelas.
Route::middleware(['auth', 'role:super_admin', 'tenant'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('tenants.activate');
    Route::post('tenants/{tenant}/plan', [TenantController::class, 'plan'])->name('tenants.plan');

    Route::post('subscription-invoices/{invoice}/pay', [SubscriptionInvoiceController::class, 'pay'])->name('subscription-invoices.pay');
    Route::post('subscription-invoices/{invoice}/void', [SubscriptionInvoiceController::class, 'void'])->name('subscription-invoices.void');

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
});
