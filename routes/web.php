<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'message' => 'Kelola kos jadi rapi: tagihan, pembayaran, dan pengingat dalam satu tempat.',
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store']);

    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store']);

    Route::get('/lupa-sandi', [PasswordResetController::class, 'forgot'])->name('password.request');
    Route::post('/lupa-sandi', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/atur-ulang-sandi/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/atur-ulang-sandi', [PasswordResetController::class, 'update'])->name('password.update');

    Route::get('/undangan/{token}', [AcceptInvitationController::class, 'show'])->name('invitation.show');
    Route::post('/undangan/{token}', [AcceptInvitationController::class, 'store'])->name('invitation.accept');
});

Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
