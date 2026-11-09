<?php

use App\Http\Controllers\Portal\ComplaintController;
use App\Http\Controllers\Portal\ContractController;
use App\Http\Controllers\Portal\HistoryController;
use App\Http\Controllers\Portal\HomeController;
use App\Http\Controllers\Portal\InvoiceController;
use App\Http\Controllers\Portal\ReceiptController;
use Illuminate\Support\Facades\Route;

// Portal penghuni lewat tautan bertoken. Dimuat di bootstrap/app.php dengan middleware `web`, prefix `p`,
// dan nama route `portal.`. Setiap rute di bawah /p/{token} melewati `portal.token` (ResolvePortalToken):
// token dicek, tenant ditetapkan, dan penghuni dari token menjadi satu-satunya sumber identitas.
// Id di URL (tagihan, kuitansi, komplain) selalu dicari lewat penghuni itu, tidak lewat route model binding.

Route::prefix('{token}')->where(['token' => '[^/]+'])->middleware(['portal.token', 'throttle:portal'])->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/riwayat', HistoryController::class)->name('history');
    Route::get('/kontrak', ContractController::class)->name('contract');

    Route::get('/tagihan/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
    Route::post('/tagihan/{invoice}/bukti', [InvoiceController::class, 'storeProof'])->whereNumber('invoice')
        ->middleware('throttle:portal-write')->name('invoices.proofs.store');

    Route::get('/kuitansi/{payment}', [ReceiptController::class, 'show'])->whereNumber('payment')->name('receipts.show');
    Route::get('/kuitansi/{payment}/pdf', [ReceiptController::class, 'pdf'])->whereNumber('payment')->name('receipts.pdf');

    Route::get('/komplain', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::post('/komplain', [ComplaintController::class, 'store'])->middleware('throttle:portal-write')->name('complaints.store');
    Route::get('/komplain/{complaint}', [ComplaintController::class, 'show'])->whereNumber('complaint')->name('complaints.show');
    Route::post('/komplain/{complaint}/komentar', [ComplaintController::class, 'comment'])->whereNumber('complaint')
        ->middleware('throttle:portal-write')->name('complaints.comment');
});
