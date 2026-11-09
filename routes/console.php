<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penagihan. Keduanya idempoten: terbit ulang tidak menghasilkan tagihan ganda, dan jam UTC ini
// sudah lewat tengah malam di seluruh zona Indonesia. Terbit dijalankan tiap jam sebagai pengulangan
// bila putaran sebelumnya gagal.
Schedule::command('invoices:generate')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('invoices:mark-overdue')->dailyAt('01:00')->withoutOverlapping()->onOneServer();
