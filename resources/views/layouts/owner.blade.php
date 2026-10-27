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
            @hasSection('nav')
                @yield('nav')
            @else
                @include('owner.partials.nav')
            @endif
        </nav>
        @auth
            <div class="p-space-md border-t border-outline-variant/40 flex flex-col gap-space-sm">
                <div class="min-w-0">
                    <div class="font-label-md text-label-md truncate">{{ auth()->user()->name }}</div>
                    <div class="font-body-sm text-body-sm text-secondary">{{ auth()->user()->role->label() }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full px-3 py-2 rounded-xl font-body-sm text-body-sm text-error hover:bg-error-container/20 transition-colors">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">logout</span>
                        <span>Keluar Akun</span>
                    </button>
                </form>
            </div>
        @endauth
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
                @if (session('status'))
                    <div class="rounded-xl bg-primary-fixed text-on-primary-fixed-variant px-space-md py-space-sm font-label-md" role="status">{{ session('status') }}</div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
