<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Tautan berisi rahasia: jangan diindeks dan jangan ikut terkirim sebagai Referer. --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Portal penghuni') · {{ $portalTenant->name }}</title>
    @include('layouts.fonts')
    @vite(['resources/css/app.css'])
</head>
<body class="bg-background font-body-md text-body-md text-on-surface">
    @php
        $tab = 'flex flex-col items-center gap-0.5 flex-1 py-2 font-label-sm text-label-sm';
        $current = fn (string ...$patterns) => request()->routeIs(...$patterns);
        $tabs = [
            ['portal.home', 'Beranda', 'home', ['portal.home']],
            ['portal.invoices.index', 'Tagihan', 'receipt_long', ['portal.invoices.*']],
            ['portal.payments.index', 'Riwayat', 'history', ['portal.payments.*']],
            ['portal.complaints.index', 'Komplain', 'build', ['portal.complaints.*']],
            ['portal.lease', 'Kontrak', 'assignment', ['portal.lease']],
        ];
    @endphp

    <header class="sticky top-0 z-30 bg-surface-container-lowest/95 backdrop-blur shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
        <div class="max-w-md mx-auto px-margin h-14 flex items-center justify-between gap-space-sm">
            <div class="min-w-0">
                <div class="font-headline-md text-headline-md text-primary truncate">{{ $portalTenant->name }}</div>
            </div>
            <div class="font-label-md text-label-md text-secondary truncate max-w-[45%]">{{ $portalResident->name }}</div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-margin pt-space-md pb-28 flex flex-col gap-space-md">
        @if (session('status'))
            <div class="rounded-xl bg-primary-fixed text-on-primary-fixed-variant px-space-md py-space-sm font-label-md" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm font-label-md" role="alert">
                @foreach ($errors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>

    <nav class="fixed bottom-0 inset-x-0 z-30 bg-surface-container-lowest border-t border-outline-variant/60" aria-label="Menu portal">
        <div class="max-w-md mx-auto flex">
            @foreach ($tabs as [$route, $label, $icon, $patterns])
                @php($active = $current(...$patterns))
                <a href="{{ route($route, ['token' => $portalToken]) }}" @if ($active) aria-current="page" @endif
                   class="{{ $tab }} {{ $active ? 'text-primary font-label-lg' : 'text-secondary' }}">
                    <span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    @stack('scripts')
</body>
</html>
