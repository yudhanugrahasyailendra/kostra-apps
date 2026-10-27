<?php

use App\Http\Controllers\Portal\ComplaintController;
use App\Http\Controllers\Portal\HomeController;
use App\Http\Controllers\Portal\InvoiceController;
use App\Http\Controllers\Portal\LeaseController;
use App\Http\Controllers\Portal\PaymentController;
use App\Http\Controllers\Portal\ProofController;
use App\Http\Controllers\Portal\QrisController;
use Illuminate\Support\Facades\Route;

// Portal penghuni tanpa akun. Prefix /p dan nama portal.* (lihat bootstrap/app.php).
//
// Satu-satunya permukaan publik aplikasi. Otorisasinya adalah token di URL (middleware portal.token): token
// menentukan tenant dan penghuni, dan SEMUA data diambil lewat penghuni itu. Parameter id di URL (tagihan,
// kuitansi, komplain) tidak pernah dipercaya tanpa pemeriksaan kepemilikan di controller.
Route::prefix('{token}')->middleware(['throttle:portal', 'portal.token'])->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/tagihan', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/tagihan/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
    Route::post('/tagihan/{invoice}/bukti', [ProofController::class, 'store'])->whereNumber('invoice')
        ->middleware('throttle:portal-upload')->name('proofs.store');

    Route::get('/pembayaran', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/kuitansi/{payment}', [PaymentController::class, 'receipt'])->whereNumber('payment')->name('payments.receipt');
    Route::get('/kuitansi/{payment}/pdf', [PaymentController::class, 'pdf'])->whereNumber('payment')->name('payments.pdf');

    Route::get('/kontrak', LeaseController::class)->name('lease');

    Route::get('/komplain', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/komplain/baru', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/komplain', [ComplaintController::class, 'store'])->middleware('throttle:portal-upload')->name('complaints.store');
    Route::get('/komplain/{complaint}', [ComplaintController::class, 'show'])->whereNumber('complaint')->name('complaints.show');
    Route::post('/komplain/{complaint}/komentar', [ComplaintController::class, 'comment'])->whereNumber('complaint')
        ->middleware('throttle:portal-write')->name('complaints.comments.store');

    Route::get('/rekening/{account}/qris', QrisController::class)->whereNumber('account')->name('accounts.qris');
});
