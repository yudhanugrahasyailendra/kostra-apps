<?php

return [

    /*
    | Zona waktu tampilan bawaan tenant. Aplikasi sendiri berjalan di UTC.
    */
    'default_timezone' => 'Asia/Makassar',

    /*
    | Paket bawaan (angka contoh dari PRD, harga final belum diputuskan).
    | Sumber kebenaran runtime adalah tabel plans; ini hanya untuk seeder dan tes.
    | null = tanpa batas. Harga dalam rupiah.
    */
    'plans' => [
        'free' => ['name' => 'Gratis', 'price_monthly' => 0, 'max_properties' => 1, 'max_rooms' => 10, 'max_users' => 1, 'monthly_message_quota' => 0],
        'starter' => ['name' => 'Starter', 'price_monthly' => 79000, 'price_yearly' => 790000, 'max_properties' => 1, 'max_rooms' => 30, 'max_users' => 2, 'monthly_message_quota' => 150, 'features' => ['reports.export']],
        'pro' => ['name' => 'Pro', 'price_monthly' => 199000, 'price_yearly' => 1990000, 'max_properties' => 3, 'max_rooms' => 100, 'max_users' => 5, 'monthly_message_quota' => 600, 'features' => ['reports.export']],
        'business' => ['name' => 'Business', 'price_monthly' => 499000, 'price_yearly' => 4990000, 'max_properties' => null, 'max_rooms' => null, 'max_users' => null, 'monthly_message_quota' => 2000, 'features' => ['reports.export']],
    ],

    /*
    | Akun Super Admin untuk SuperAdminSeeder. Tanpa nilai bawaan; isi lewat .env.
    */
    'super_admin' => [
        'name' => env('SUPERADMIN_NAME', 'Super Admin'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    'trial_days' => 14,
    'grace_days' => 7,

    /*
    | Tagihan perpanjangan dibuat sekian hari sebelum periode berakhir.
    */
    'renewal_notice_days' => 7,

    /*
    | Fitur yang dikenal paket (plans.features). Hanya yang tercantum di sini yang bisa dipakai Super Admin.
    | reports.export = ekspor laporan ke Excel dan PDF.
    */
    'features' => [
        'reports.export' => 'Ekspor laporan ke Excel dan PDF',
    ],

    /*
    | Rekening tujuan transfer langganan Kostra (pembayaran semi-manual; Super Admin mencatatnya).
    */
    'subscription' => [
        'bank' => env('SUBSCRIPTION_BANK', 'Isi SUBSCRIPTION_BANK di .env'),
        'account_number' => env('SUBSCRIPTION_ACCOUNT_NUMBER', ''),
        'account_name' => env('SUBSCRIPTION_ACCOUNT_NAME', ''),
    ],

    /*
    | Nilai bawaan billing_settings untuk tenant baru.
    */
    'billing' => [
        'invoice_prefix' => 'INV',
        'issue_day' => 1,
        'due_day' => 10,
        'reminder_offsets' => [-3, 0, 3],
        // Awalan nomor kuitansi tetap (billing_settings tidak punya kolom untuk ini).
        'receipt_prefix' => 'KWT',
        'send_window_start' => '08:00',
        'send_window_end' => '20:00',
    ],

    /*
    | Penyedia WhatsApp. Pengembangan dan tes wajib memakai "fake".
    */
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'fake'),
    ],

    /*
    | Pengingat kontrak segera habis: berapa hari sebelum end_date.
    */
    'lease_expiring_days' => [30, 14, 7],

    /*
    | Portal penghuni lewat tautan token. Umur tautan dalam hari.
    */
    'portal' => [
        'token_days' => 60,
        // Penghuni satu kos biasanya berbagi satu IP publik (Wi-Fi kos), jadi batasnya longgar.
        'requests_per_minute' => 240,
    ],

    'attachments' => [
        'disk' => 'private',
        'max_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
    ],

];
