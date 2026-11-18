<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Tidak ada halaman sambutan: tamu langsung ke form masuk, pengguna yang sudah masuk ke ruang kerjanya.
Route::get('/', function (Request $request) {
    $user = $request->user();

    return match (true) {
        $user === null => redirect()->route('login'),
        $user->tenant_id !== null => redirect()->route('owner.dashboard'),
        $user->isSuperAdmin() => redirect()->route('admin.tenants.index'),
        // Akun tanpa tenant dan bukan super admin tidak punya ruang kerja; keluar agar tidak berputar.
        default => tap(redirect()->route('login'), function () use ($request) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }),
    };
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store']);

    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/lupa-sandi', [PasswordResetController::class, 'forgot'])->name('password.request');
    Route::post('/lupa-sandi', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/atur-ulang-sandi/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/atur-ulang-sandi', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');

    Route::get('/undangan/{token}', [AcceptInvitationController::class, 'show'])->name('invitation.show');
    Route::post('/undangan/{token}', [AcceptInvitationController::class, 'store'])->middleware('throttle:10,1')->name('invitation.accept');
});

Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
