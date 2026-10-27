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
        'starter' => ['name' => 'Starter', 'price_monthly' => 79000, 'max_properties' => 1, 'max_rooms' => 30, 'max_users' => 2, 'monthly_message_quota' => 150],
        'pro' => ['name' => 'Pro', 'price_monthly' => 199000, 'max_properties' => 3, 'max_rooms' => 100, 'max_users' => 5, 'monthly_message_quota' => 600],
        'business' => ['name' => 'Business', 'price_monthly' => 499000, 'max_properties' => null, 'max_rooms' => null, 'max_users' => null, 'monthly_message_quota' => 2000],
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
    | Nilai bawaan billing_settings untuk tenant baru.
    */
    'billing' => [
        'invoice_prefix' => 'INV',
        'issue_day' => 1,
        'due_day' => 10,
        'reminder_offsets' => [-3, 0, 3],
        'send_window_start' => '08:00',
        'send_window_end' => '20:00',
    ],

    /*
    | Penyedia WhatsApp. Pengembangan dan tes wajib memakai "fake".
    */
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'fake'),
    ],

    'attachments' => [
        'disk' => 'private',
        'max_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
    ],

];
