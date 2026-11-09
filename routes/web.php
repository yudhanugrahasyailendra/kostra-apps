<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'message' => 'Kelola kos jadi rapi: tagihan, pembayaran, dan pengingat dalam satu tempat.',
    ]);
})->name('home');
