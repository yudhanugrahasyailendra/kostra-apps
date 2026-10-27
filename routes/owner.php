<?php

use App\Http\Controllers\Owner\AttachmentController;
use App\Http\Controllers\Owner\BillingSettingsController;
use App\Http\Controllers\Owner\ComplaintController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\ExpenseController;
use App\Http\Controllers\Owner\ExportController;
use App\Http\Controllers\Owner\InvoiceController;
use App\Http\Controllers\Owner\LeaseAttachmentController;
use App\Http\Controllers\Owner\LeaseController;
use App\Http\Controllers\Owner\MessageController;
use App\Http\Controllers\Owner\MessageTemplateController;
use App\Http\Controllers\Owner\PaymentAccountController;
use App\Http\Controllers\Owner\PaymentController;
use App\Http\Controllers\Owner\PaymentProofController;
use App\Http\Controllers\Owner\PropertyController;
use App\Http\Controllers\Owner\PropertyPhotoController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\ResidentAttachmentController;
use App\Http\Controllers\Owner\ResidentController;
use App\Http\Controllers\Owner\ResidentPortalController;
use App\Http\Controllers\Owner\ResidentWhatsAppController;
use App\Http\Controllers\Owner\RoomController;
use App\Http\Controllers\Owner\RoomTypeController;
use App\Http\Controllers\Owner\SettingsController;
use App\Http\Controllers\Owner\StaffController;
use App\Http\Controllers\Owner\SubscriptionController;
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
    Route::post('residents/{resident}/whatsapp', [ResidentWhatsAppController::class, 'update'])->name('residents.whatsapp');

    // Tautan portal penghuni: kirim lewat WhatsApp, cabut satu, atau cabut semua. Token asli tidak pernah ditampilkan.
    Route::post('residents/{resident}/portal', [ResidentPortalController::class, 'send'])->name('residents.portal.send');
    Route::post('residents/{resident}/portal/revoke', [ResidentPortalController::class, 'revokeAll'])->name('residents.portal.revoke-all');
    Route::delete('residents/{resident}/portal/{portalToken}', [ResidentPortalController::class, 'revoke'])->name('residents.portal.revoke');

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

    // Pesan WhatsApp: log memuat nomor dan isi pesan, jadi hanya untuk pemilik atau staf berizin pengaturan (Policy juga memeriksa).
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('messages/{log}/resend', [MessageController::class, 'resend'])->name('messages.resend');
        Route::get('message-templates', [MessageTemplateController::class, 'index'])->name('message-templates.index');
        Route::get('message-templates/{key}/edit', [MessageTemplateController::class, 'edit'])->name('message-templates.edit');
        Route::put('message-templates/{key}', [MessageTemplateController::class, 'update'])->name('message-templates.update');
    });

    // Tiket komplain tidak punya rute hapus: hanya ditutup (selesai atau ditolak).
    Route::resource('complaints', ComplaintController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('complaints/{complaint}/status', [ComplaintController::class, 'status'])->name('complaints.status');
    Route::post('complaints/{complaint}/assign', [ComplaintController::class, 'assign'])->name('complaints.assign');
    Route::post('complaints/{complaint}/comments', [ComplaintController::class, 'comment'])->name('complaints.comments.store');
    Route::post('complaints/{complaint}/photos', [ComplaintController::class, 'photo'])->name('complaints.photos.store');

    // Keuangan: pengeluaran dan laporan hanya untuk pemilik atau staf berizin finance.view (Policy dan Gate juga memeriksa).
    Route::middleware('can:finance.view')->group(function () {
        Route::resource('expenses', ExpenseController::class)->except(['show']);
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    });

    Route::post('rooms/bulk', [RoomController::class, 'bulk'])->name('rooms.bulk');
    Route::post('rooms/import', [RoomController::class, 'import'])->name('rooms.import');
    Route::resource('rooms', RoomController::class)->except(['show', 'create']);

    // Langganan dan ekspor data: hanya pemilik. Ekspor sengaja tidak memeriksa paket atau status langganan.
    Route::middleware('role:owner')->group(function () {
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::post('subscription/trial', [SubscriptionController::class, 'trial'])->name('subscription.trial');
        Route::post('subscription/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
        Route::post('subscription/downgrade', [SubscriptionController::class, 'downgrade'])->name('subscription.downgrade');

        Route::get('export', [ExportController::class, 'index'])->name('export.index');
        // Berkas dibangun dari seluruh data tenant: dibatasi supaya tidak dipakai membebani server.
        Route::get('export/download', [ExportController::class, 'download'])->middleware('throttle:3,1')->name('export.download');
    });

    Route::middleware('role:owner')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/invitations', [StaffController::class, 'invite'])->name('invite');
        Route::delete('/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])->name('invitations.revoke');
        Route::put('/{staff}', [StaffController::class, 'updatePermissions'])->name('update');
    });
});
