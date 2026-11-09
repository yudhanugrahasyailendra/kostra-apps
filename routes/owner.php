<?php

use App\Http\Controllers\Owner\AttachmentController;
use App\Http\Controllers\Owner\AuditLogController;
use App\Http\Controllers\Owner\BillingSettingsController;
use App\Http\Controllers\Owner\ComplaintController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\DataExportController;
use App\Http\Controllers\Owner\ExpenseController;
use App\Http\Controllers\Owner\InvoiceController;
use App\Http\Controllers\Owner\LeaseCheckOutController;
use App\Http\Controllers\Owner\LeaseController;
use App\Http\Controllers\Owner\LeaseRenewController;
use App\Http\Controllers\Owner\LeaseTransferController;
use App\Http\Controllers\Owner\PaymentAccountController;
use App\Http\Controllers\Owner\PaymentController;
use App\Http\Controllers\Owner\PaymentProofController;
use App\Http\Controllers\Owner\PropertyController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\ResidentController;
use App\Http\Controllers\Owner\RoomController;
use App\Http\Controllers\Owner\RoomTypeController;
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

Route::get('/kamar', [RoomController::class, 'index'])->name('rooms.index');
Route::post('/kamar', [RoomController::class, 'store'])->name('rooms.store');
Route::post('/kamar/massal', [RoomController::class, 'bulk'])->name('rooms.bulk');
Route::post('/kamar/impor', [RoomController::class, 'import'])->name('rooms.import');
Route::get('/kamar/impor/templat', [RoomController::class, 'template'])->name('rooms.template');
Route::patch('/kamar/{room}', [RoomController::class, 'update'])->name('rooms.update');
Route::delete('/kamar/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

Route::get('/penghuni', [ResidentController::class, 'index'])->name('residents.index');
Route::post('/penghuni', [ResidentController::class, 'store'])->name('residents.store');
Route::post('/penghuni/impor', [ResidentController::class, 'import'])->name('residents.import');
Route::get('/penghuni/impor/templat', [ResidentController::class, 'template'])->name('residents.template');
Route::get('/penghuni/{resident}', [ResidentController::class, 'show'])->name('residents.show');
Route::patch('/penghuni/{resident}', [ResidentController::class, 'update'])->name('residents.update');
Route::delete('/penghuni/{resident}', [ResidentController::class, 'destroy'])->name('residents.destroy');
Route::post('/penghuni/{resident}/lampiran', [AttachmentController::class, 'store'])
    ->middleware('throttle:30,1')->name('residents.attachments.store');

// Tautan lampiran dibuat bertanda tangan relatif (tanpa host) dan berumur 5 menit; lihat AttachmentResource.
Route::get('/lampiran/{attachment}', [AttachmentController::class, 'show'])
    ->middleware(['signed:relative', 'throttle:60,1'])->name('attachments.show');
Route::delete('/lampiran/{attachment}', [AttachmentController::class, 'destroy'])
    ->middleware('throttle:30,1')->name('attachments.destroy');

Route::get('/properti', [PropertyController::class, 'index'])->name('properties.index');
Route::post('/properti', [PropertyController::class, 'store'])->name('properties.store');
Route::patch('/properti/{property}', [PropertyController::class, 'update'])->name('properties.update');
Route::delete('/properti/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');

Route::get('/tipe-kamar', [RoomTypeController::class, 'index'])->name('room-types.index');
Route::post('/tipe-kamar', [RoomTypeController::class, 'store'])->name('room-types.store');
Route::patch('/tipe-kamar/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update');
Route::delete('/tipe-kamar/{roomType}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');

Route::get('/kontrak', [LeaseController::class, 'index'])->name('leases.index');
Route::get('/kontrak/baru', [LeaseController::class, 'create'])->name('leases.create');
Route::post('/kontrak', [LeaseController::class, 'store'])->name('leases.store');
Route::get('/kontrak/{lease}', [LeaseController::class, 'show'])->name('leases.show');
Route::get('/kontrak/{lease}/check-out', [LeaseCheckOutController::class, 'create'])->name('leases.checkout.create');
Route::post('/kontrak/{lease}/check-out', [LeaseCheckOutController::class, 'store'])->name('leases.checkout.store');
Route::post('/kontrak/{lease}/perpanjang', [LeaseRenewController::class, 'store'])->name('leases.renew');
Route::post('/kontrak/{lease}/pindah-kamar', [LeaseTransferController::class, 'store'])->name('leases.transfer');

Route::get('/tagihan', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('/tagihan/baru', [InvoiceController::class, 'create'])->name('invoices.create');
Route::post('/tagihan', [InvoiceController::class, 'store'])->name('invoices.store');
Route::get('/tagihan/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::post('/tagihan/{invoice}/penyesuaian', [InvoiceController::class, 'adjust'])->name('invoices.adjust');
Route::post('/tagihan/{invoice}/batal', [InvoiceController::class, 'void'])->name('invoices.void');

Route::get('/pembayaran', [PaymentController::class, 'index'])->name('payments.index');
Route::get('/pembayaran/baru', [PaymentController::class, 'create'])->name('payments.create');
Route::post('/pembayaran', [PaymentController::class, 'store'])->name('payments.store');
Route::get('/pembayaran/{payment}/kuitansi', [PaymentController::class, 'receipt'])->name('payments.receipt');
Route::get('/pembayaran/{payment}/kuitansi.pdf', [PaymentController::class, 'receiptPdf'])->name('payments.receipt.pdf');
Route::post('/pembayaran/{payment}/batal', [PaymentController::class, 'void'])->name('payments.void');

Route::get('/pengeluaran', [ExpenseController::class, 'index'])->name('expenses.index');
Route::post('/pengeluaran', [ExpenseController::class, 'store'])
    ->middleware('throttle:30,1')->name('expenses.store');
Route::post('/pengeluaran/{expense}', [ExpenseController::class, 'update'])
    ->middleware('throttle:30,1')->name('expenses.update');
Route::delete('/pengeluaran/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

Route::get('/komplain', [ComplaintController::class, 'index'])->name('complaints.index');
Route::post('/komplain', [ComplaintController::class, 'store'])
    ->middleware('throttle:30,1')->name('complaints.store');
Route::get('/komplain/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
Route::post('/komplain/{complaint}/status', [ComplaintController::class, 'status'])->name('complaints.status');
Route::post('/komplain/{complaint}/penanggung-jawab', [ComplaintController::class, 'assign'])->name('complaints.assign');
Route::post('/komplain/{complaint}/komentar', [ComplaintController::class, 'comment'])
    ->middleware('throttle:60,1')->name('complaints.comment');
// Sengaja tanpa route hapus: tiket ditutup lewat status, tidak dihapus.

Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
Route::get('/laporan/xlsx', [ReportController::class, 'xlsx'])->middleware('throttle:20,1')->name('reports.xlsx');
Route::get('/laporan/pdf', [ReportController::class, 'pdf'])->middleware('throttle:20,1')->name('reports.pdf');

Route::get('/bukti-transfer', [PaymentProofController::class, 'index'])->name('proofs.index');
Route::post('/tagihan/{invoice}/bukti', [PaymentProofController::class, 'store'])
    ->middleware('throttle:30,1')->name('invoices.proofs.store');
Route::post('/bukti-transfer/{proof}/setujui', [PaymentProofController::class, 'approve'])->name('proofs.approve');
Route::post('/bukti-transfer/{proof}/tolak', [PaymentProofController::class, 'reject'])->name('proofs.reject');

Route::get('/pengaturan/rekening', [PaymentAccountController::class, 'index'])->name('payment-accounts.index');
Route::post('/pengaturan/rekening', [PaymentAccountController::class, 'store'])->name('payment-accounts.store');
Route::patch('/pengaturan/rekening/{paymentAccount}', [PaymentAccountController::class, 'update'])->name('payment-accounts.update');

Route::get('/pengaturan/penagihan', [BillingSettingsController::class, 'show'])->name('settings.billing');
Route::patch('/pengaturan/penagihan', [BillingSettingsController::class, 'update'])->name('settings.billing.update');
