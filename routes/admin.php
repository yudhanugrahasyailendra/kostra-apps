<?php

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

// Panel Super Admin. Prefix /admin dan nama admin.* (lihat bootstrap/app.php).
Route::middleware(['auth', 'role:super_admin', 'tenant'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
});
