<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') · Kostra</title>
    @include('layouts.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface min-h-screen flex items-center justify-center p-margin">
    <main class="w-full max-w-md">
        <div class="mb-space-lg text-center">
            <span class="font-headline-lg text-headline-lg text-primary tracking-tight">Kostra</span>
            <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Manajemen Kos</p>
        </div>
        @yield('content')
    </main>
</body>
</html>
