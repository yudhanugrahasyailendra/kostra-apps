<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Kostra
|--------------------------------------------------------------------------
|
| Nilai bawaan aplikasi. Batas paket di sini hanya bawaan untuk PlanSeeder
| (sumber kebenaran runtime adalah tabel `plans`). Semua uang dalam integer
| rupiah. Kuota pesan dan harga paket sengaja belum diisi: tarif Meta untuk
| Indonesia belum diverifikasi dan harga final menunggu wawancara pilot
| (PRD bagian 14, pertanyaan terbuka).
|
*/

return [

    // Database menyimpan UTC; tampilan dan laporan memakai zona ini (default tenant).
    'display_timezone' => env('KOSTRA_DISPLAY_TIMEZONE', 'Asia/Makassar'),

    // Batas paket sesuai PRD bagian 1. null = tanpa batas.
    'plans' => [
        'free' => ['max_properties' => 1, 'max_rooms' => 10],
        'starter' => ['max_properties' => 1, 'max_rooms' => 30],
        'pro' => ['max_properties' => 3, 'max_rooms' => 100],
        'business' => ['max_properties' => null, 'max_rooms' => null],
    ],

    // Nilai bawaan billing_settings untuk tenant baru; pemilik dapat mengubahnya.
    'billing' => [
        'invoice_prefix' => 'INV',
        'issue_day' => 1,
        'due_day' => 5,
        'reminder_offsets' => [-3, 0, 3],
        'send_window_start' => '08:00',
        'send_window_end' => '20:00',
    ],

    // Masa percobaan dan tenggang langganan (PRD LGN-03).
    'subscription' => [
        'trial_days' => 14,
        'grace_days' => 7,
    ],

];
