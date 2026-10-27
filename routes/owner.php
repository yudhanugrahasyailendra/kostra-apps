<?php

use App\Http\Controllers\Owner\AttachmentController;
use App\Http\Controllers\Owner\BillingSettingsController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\InvoiceController;
use App\Http\Controllers\Owner\LeaseAttachmentController;
use App\Http\Controllers\Owner\LeaseController;
use App\Http\Controllers\Owner\PaymentAccountController;
use App\Http\Controllers\Owner\PaymentController;
use App\Http\Controllers\Owner\PaymentProofController;
use App\Http\Controllers\Owner\PropertyController;
use App\Http\Controllers\Owner\PropertyPhotoController;
use App\Http\Controllers\Owner\ResidentAttachmentController;
use App\Http\Controllers\Owner\ResidentController;
use App\Http\Controllers\Owner\RoomController;
use App\Http\Controllers\Owner\RoomTypeController;
use App\Http\Controllers\Owner\SettingsController;
use App\Http\Controllers\Owner\StaffController;
use Illuminate\Support\Facades\Route;

// Panel pemilik dan staf. Prefix /owner dan nama owner.* (lihat bootstrap/app.php).
Route::middleware(['auth', 'role:owner,staff', 'tenant', 'tenant.active'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/settings', SettingsController::class)->middleware('can:settings.manage')->name('settings');
    Route::middleware('can:settings.manage')->prefix('settings/payment-accounts')->name('payment-accounts.')->group(function () {
        Route::get('/', [PaymentAccountController::class, 'index'])->name('index');
        Route::post('/', [PaymentAccountController::class, 'store'])->name('store');
        Route::get('/{account}/edit', [PaymentAccountController::class, 'edit'])->name('edit');
        Route::put('/{account}', [PaymentAccountController::class, 'update'])->name('update');
        Route::post('/{account}/qris', [PaymentAccountController::class, 'qris'])->name('qris');
    });
    Route::middleware('can:settings.manage')->prefix('settings/billing')->name('settings.billing')->group(function () {
        Route::get('/', [BillingSettingsController::class, 'edit']);
        Route::put('/', [BillingSettingsController::class, 'update'])->name('.update');
    });

    // Hak tulis diputuskan Policy (pemilik); staf hanya melihat.
    Route::resource('properties', PropertyController::class)->except(['show', 'create']);
    Route::resource('room-types', RoomTypeController::class)->except(['show', 'create'])
        ->parameters(['room-types' => 'roomType']);

    Route::post('properties/{property}/photo', [PropertyPhotoController::class, 'store'])->name('properties.photo.store');

    Route::post('residents/import', [ResidentController::class, 'import'])->name('residents.import');
    Route::resource('residents', ResidentController::class);
    Route::post('residents/{resident}/attachments', [ResidentAttachmentController::class, 'store'])->name('residents.attachments.store');

    // Lampiran disajikan hanya lewat tautan bertanda tangan berumur pendek, dan tetap melewati Policy.
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->middleware('signed')->name('attachments.show');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::resource('leases', LeaseController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('leases/{lease}/check-out', [LeaseController::class, 'checkOut'])->name('leases.check-out');
    Route::post('leases/{lease}/renew', [LeaseController::class, 'renew'])->name('leases.renew');
    Route::post('leases/{lease}/transfer', [LeaseController::class, 'transfer'])->name('leases.transfer');
    Route::post('leases/{lease}/attachments', [LeaseAttachmentController::class, 'store'])->name('leases.attachments.store');

    // Tagihan tidak punya rute hapus: koreksi lewat penyesuaian, pembatalan lewat void.
    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('invoices/{invoice}/adjustments', [InvoiceController::class, 'adjust'])->name('invoices.adjust');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

    // Pembayaran tidak punya rute hapus: hanya dibatalkan (void) dengan alasan.
    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('payments/{payment}/receipt.pdf', [PaymentController::class, 'receiptPdf'])->name('payments.receipt.pdf');
    Route::post('payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

    Route::get('proofs', [PaymentProofController::class, 'index'])->name('proofs.index');
    Route::post('invoices/{invoice}/proofs', [PaymentProofController::class, 'store'])->name('invoices.proofs.store');
    Route::post('proofs/{proof}/approve', [PaymentProofController::class, 'approve'])->name('proofs.approve');
    Route::post('proofs/{proof}/reject', [PaymentProofController::class, 'reject'])->name('proofs.reject');

    Route::post('rooms/bulk', [RoomController::class, 'bulk'])->name('rooms.bulk');
    Route::post('rooms/import', [RoomController::class, 'import'])->name('rooms.import');
    Route::resource('rooms', RoomController::class)->except(['show', 'create']);

    Route::middleware('role:owner')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/invitations', [StaffController::class, 'invite'])->name('invite');
        Route::delete('/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])->name('invitations.revoke');
        Route::put('/{staff}', [StaffController::class, 'updatePermissions'])->name('update');
    });
});
