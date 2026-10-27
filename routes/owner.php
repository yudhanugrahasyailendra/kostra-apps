<?php

use App\Http\Controllers\Owner\AttachmentController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\LeaseAttachmentController;
use App\Http\Controllers\Owner\LeaseController;
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
