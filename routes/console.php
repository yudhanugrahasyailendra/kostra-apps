<?php

use Illuminate\Support\Facades\Schedule;

// Penerbitan tagihan bulanan dan penandaan terlambat. Satu job per tenant (lihat RunTenantBilling);
// idempoten, jadi aman bila terlewat atau berjalan dua kali.
Schedule::command('billing:run')
    ->dailyAt('01:00')
    ->name('billing:run')
    ->onOneServer()
    ->withoutOverlapping();
