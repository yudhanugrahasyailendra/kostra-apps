<?php

use App\Services\WhatsApp\FakeWhatsAppProvider;

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

    // IP proxy balik tepercaya, dipisah koma, atau * bila server hanya dijangkau lewat proxy (lihat docs/deploy.md).
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

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

    // Notifikasi WhatsApp (NTF-01). Penyedia dipilih lewat KOSTRA_WHATSAPP_PROVIDER; daftar di bawah memetakan
    // nama ke kelas yang mengimplementasikan WhatsAppProvider. Hanya `fake` yang tersedia sampai penyedia resmi
    // diputuskan; penyedia baru cukup menambah satu adapter dan satu baris di sini.
    'whatsapp' => [
        'provider' => env('KOSTRA_WHATSAPP_PROVIDER', 'fake'),
        'providers' => [
            'fake' => FakeWhatsAppProvider::class,
        ],
        // Percobaan pengiriman per pesan (termasuk yang pertama) sebelum berstatus failed.
        'max_attempts' => 3,
        // Jeda detik sebelum percobaan ke-2, ke-3, dan seterusnya.
        'retry_backoff_seconds' => [60, 300],
    ],

    // Portal penghuni (PRT-01). Tautan berumur terbatas; percobaan token salah dibatasi per IP per menit.
    'portal' => [
        'token_ttl_days' => 90,
        'invalid_attempts_per_minute' => 10,
        'requests_per_minute' => 120,
        'writes_per_minute' => 10,
    ],

    // Super Admin awal untuk SuperAdminSeeder. Dibaca lewat config agar tetap jalan saat config:cache.
    'admin' => [
        'email' => env('KOSTRA_ADMIN_EMAIL'),
        'password' => env('KOSTRA_ADMIN_PASSWORD'),
    ],

    // Masa percobaan dan tenggang langganan (PRD LGN-03).
    'subscription' => [
        'trial_days' => 14,
        'grace_days' => 7,
    ],

];
