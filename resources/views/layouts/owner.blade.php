<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel') · Kostra</title>
    @include('layouts.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background font-body-md text-body-md text-on-surface">
    {{-- Laci ponsel tanpa JavaScript: checkbox + peer. Sidebar tetap tampil di layar lg ke atas. --}}
    <input type="checkbox" id="nav-toggle" class="peer sr-only">

    <label for="nav-toggle" class="fixed inset-0 z-40 bg-inverse-surface/40 hidden peer-checked:block lg:hidden!" aria-label="Tutup menu"></label>

    <aside class="fixed left-0 top-0 h-full w-72 bg-surface-container-lowest shadow-[0_1px_8px_rgba(0,0,0,0.04)] z-50 flex flex-col -translate-x-full peer-checked:translate-x-0 lg:translate-x-0 motion-safe:transition-transform">
        <div class="p-space-md flex flex-col">
            <span class="font-headline-md text-headline-md text-primary tracking-tight">Kostra</span>
            <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Manajemen Kos</span>
        </div>
        <nav class="flex flex-col gap-1 px-space-md py-space-xs flex-1 overflow-y-auto" aria-label="Menu utama">
            {{-- Menu diisi per fase. --}}
        </nav>
    </aside>

    <div class="lg:pl-72">
        <header class="fixed top-0 left-0 lg:left-72 right-0 h-16 bg-surface-container-lowest/90 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)] z-30 flex items-center gap-space-md px-margin lg:px-space-lg">
            <label for="nav-toggle" class="lg:hidden p-2 rounded-xl text-secondary hover:bg-surface-container cursor-pointer" aria-label="Buka menu">
                <span class="material-symbols-outlined">menu</span>
            </label>
            <h1 class="font-headline-md text-headline-md truncate">@yield('title', 'Panel')</h1>
        </header>

        <main class="w-full pt-16 px-margin lg:px-gutter-desktop min-h-screen">
            <div class="flex flex-col w-full py-space-lg space-y-space-lg">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
