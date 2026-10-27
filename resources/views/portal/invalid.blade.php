<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Tautan tidak berlaku · Kostra</title>
    @include('layouts.fonts')
    @vite(['resources/css/app.css'])
</head>
<body class="bg-background font-body-md text-body-md text-on-surface">
    {{-- Satu halaman untuk SEMUA kegagalan (token salah, dicabut, kedaluwarsa, penghuni atau tenant tidak ada,
         terlalu banyak percobaan): tidak ada petunjuk tentang alasannya. --}}
    <main class="max-w-md mx-auto px-margin py-space-xl flex flex-col gap-space-md text-center">
        <span class="material-symbols-outlined text-primary text-5xl" aria-hidden="true">link_off</span>
        <h1 class="font-headline-lg text-headline-lg">Tautan tidak berlaku</h1>
        <p class="text-on-surface-variant">Tautan ini tidak dapat dibuka. Minta tautan baru kepada pengelola kos Anda lewat WhatsApp.</p>
    </main>
</body>
</html>
