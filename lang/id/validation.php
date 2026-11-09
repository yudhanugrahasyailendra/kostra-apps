<?php

return [
    'array' => ':Attribute harus berupa daftar.',
    'boolean' => ':Attribute harus benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'max' => [
        'string' => ':Attribute tidak boleh lebih dari :max karakter.',
        'array' => ':Attribute tidak boleh lebih dari :max item.',
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
    ],
    'min' => [
        'string' => ':Attribute minimal :min karakter.',
        'array' => ':Attribute minimal :min item.',
        'numeric' => ':Attribute minimal :min.',
    ],
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':Attribute wajib diisi.',
    'required_without' => ':Attribute wajib diisi bila :values kosong.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah terdaftar.',
    'password' => [
        'letters' => ':Attribute harus memuat huruf.',
        'mixed' => ':Attribute harus memuat huruf besar dan kecil.',
        'numbers' => ':Attribute harus memuat angka.',
        'symbols' => ':Attribute harus memuat simbol.',
        'uncompromised' => ':Attribute pernah bocor di internet. Pilih yang lain.',
    ],

    'custom' => [],

    'attributes' => [
        'name' => 'nama',
        'kos_name' => 'nama kos',
        'email' => 'email',
        'phone' => 'nomor WhatsApp',
        'password' => 'kata sandi',
        'login' => 'email atau nomor WhatsApp',
        'permissions' => 'izin',
    ],
];
