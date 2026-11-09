<?php

use App\Http\Controllers\Owner\AttachmentController;
use App\Http\Controllers\Owner\AuditLogController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\DataExportController;
use App\Http\Controllers\Owner\PropertyController;
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
