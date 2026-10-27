<?php

use Illuminate\Support\Facades\Schedule;

// Penerbitan tagihan bulanan dan penandaan terlambat. Satu job per tenant (lihat RunTenantBilling);
// idempoten, jadi aman bila terlewat atau berjalan dua kali.
Schedule::command('billing:run')
    ->dailyAt('01:00')
    ->name('billing:run')
    ->onOneServer()
    ->withoutOverlapping();

// Pengingat tagihan dan kontrak. Satu job per tenant (lihat SendTenantReminders); tidak mengirim ganda pada hari yang sama,
// dan pesan di luar jam kirim tenant ditunda oleh lapisan pengiriman. 02:00 UTC = 09:00 sampai 11:00 di Indonesia.
Schedule::command('messages:reminders')
    ->dailyAt('02:00')
    ->name('messages:reminders')
    ->onOneServer()
    ->withoutOverlapping();
