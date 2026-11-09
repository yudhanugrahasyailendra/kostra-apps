<?php

use App\Http\Controllers\Admin\SubscriptionInvoiceController;
use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

// Panel Super Admin Kostra. Dimuat di bootstrap/app.php dengan middleware `web`, `auth`,
// `super_admin`, prefix `admin`, dan nama route `admin.`. Tidak memakai SetCurrentTenant:
// pembacaan lintas tenant dilakukan eksplisit lewat TenantContext (unscoped atau runAs).

Route::redirect('/', '/admin/tenant')->name('home');

Route::get('/tenant', [TenantController::class, 'index'])->name('tenants.index');
Route::get('/tenant/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
Route::post('/tenant/{tenant}/tangguhkan', [TenantController::class, 'suspend'])->name('tenants.suspend');
Route::post('/tenant/{tenant}/aktifkan', [TenantController::class, 'activate'])->name('tenants.activate');
Route::post('/tenant/{tenant}/tagihan/{invoice}/bayar', [SubscriptionInvoiceController::class, 'pay'])
    ->whereNumber('invoice')->middleware('throttle:30,1')->name('tenants.invoices.pay');
