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

    <aside class="fixed left-0 top-0 h-full w-72 bg-surface-container-lowest shadow-[0_1px_8px_rgba(0,0,0,0.04)] z-50 flex flex-col justify-between -translate-x-full peer-checked:translate-x-0 lg:translate-x-0 motion-safe:transition-transform">
        <div class="flex flex-col flex-1 min-h-0 overflow-y-auto">
            <div class="p-space-md flex items-center gap-space-sm">
                <span class="w-8 h-8 rounded-lg bg-primary text-on-primary flex items-center justify-center" aria-hidden="true">
                    <span class="material-symbols-outlined text-lg">apartment</span>
                </span>
                <div class="flex flex-col">
                    <span class="font-headline-md text-headline-md text-primary tracking-tight">Kostra</span>
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Manajemen Kos</span>
                </div>
            </div>

            @if ($shell)
                <div class="px-space-md mb-space-sm">
                    <div class="p-space-sm rounded-xl bg-surface-container-low flex flex-col gap-space-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-label-sm text-label-sm text-secondary uppercase">Properti Aktif</span>
                            <span class="px-2 py-0.5 rounded-full bg-primary-fixed text-on-primary-fixed-variant font-label-sm text-label-sm">{{ $shell['planName'] }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-label-lg text-label-lg text-on-surface truncate">{{ $shell['tenantName'] }}</span>
                            <span class="material-symbols-outlined text-lg text-secondary shrink-0" aria-hidden="true">unfold_more</span>
                        </div>
                        <span class="font-body-sm text-body-sm text-secondary angka">{{ $shell['rooms'] }} Kamar Terdaftar</span>
                    </div>
                </div>
            @endif

            <nav class="flex flex-col gap-1 px-space-md py-space-xs flex-1" aria-label="Menu utama">
                @hasSection('nav')
                    @yield('nav')
                @else
                    @include('owner.partials.nav')
                @endif
            </nav>
        </div>

        <div class="p-space-md flex flex-col gap-space-sm">
            @if ($shell)
                <div class="p-space-sm rounded-xl bg-surface-container-low flex flex-col gap-space-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-label-sm text-label-sm text-secondary uppercase">Kapasitas Paket</span>
                        @can('subscription.manage')
                            <a href="{{ route('owner.subscription.show') }}" class="font-label-sm text-label-sm text-primary hover:underline font-bold">Upgrade</a>
                        @endcan
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-label-md text-label-md text-on-surface">Paket {{ $shell['planName'] }}</span>
                        <span class="font-label-sm text-label-sm text-secondary angka">
                            {{ $shell['rooms'] }}{{ $shell['roomLimit'] !== null ? '/'.$shell['roomLimit'] : '' }} Kamar
                        </span>
                    </div>
                    @if ($shell['roomLimit'])
                        <div class="meter" role="img" aria-label="Pemakaian kamar {{ $shell['rooms'] }} dari {{ $shell['roomLimit'] }}">
                            <span style="width: {{ min(100, round($shell['rooms'] / $shell['roomLimit'] * 100)) }}%"></span>
                        </div>
                    @endif
                </div>
            @endif

            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full px-3 py-2 rounded-xl font-label-md text-label-md font-bold text-error hover:bg-error-container/20 transition-colors">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">logout</span>
                        <span>Keluar Akun</span>
                    </button>
                </form>
            @endauth
        </div>
    </aside>

    <div class="lg:pl-72">
        <header class="fixed top-0 left-0 lg:left-72 right-0 h-16 bg-surface-container-lowest/90 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)] z-30 flex items-center justify-between gap-space-md px-margin lg:px-space-lg">
            <div class="flex items-center gap-space-sm min-w-0">
                <label for="nav-toggle" class="lg:hidden p-2 rounded-xl text-secondary hover:bg-surface-container cursor-pointer" aria-label="Buka menu">
                    <span class="material-symbols-outlined">menu</span>
                </label>
                <h1 class="font-headline-md text-headline-md truncate">@yield('title', 'Panel')</h1>
            </div>

            <div class="flex items-center gap-space-md">
                @if ($shell)
                    @can('settings.manage')
                        <a href="{{ route('owner.messages.index') }}" class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-full bg-surface-container-low text-secondary font-label-sm text-label-sm hover:bg-surface-container transition-colors">
                            <span class="material-symbols-outlined text-primary text-base" aria-hidden="true">send</span>
                            <span>WA Kuota: <strong class="text-on-surface angka">{{ $shell['quotaLeft'] }} tersisa</strong></span>
                        </a>
                    @endcan
                    @can('record', App\Models\Payment::class)
                        <a href="{{ route('owner.payments.create') }}" class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container transition-all shadow-[0_1px_3px_rgba(15,23,42,0.05)]">
                            <span class="material-symbols-outlined text-base" aria-hidden="true">add_circle</span>
                            <span>Catat Pembayaran</span>
                        </a>
                    @endcan
                @endif
                @auth
                    <div class="flex items-center gap-space-sm">
                        <span class="w-9 h-9 rounded-full bg-primary-fixed text-on-primary-fixed-variant flex items-center justify-center font-label-lg text-label-lg" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <div class="hidden md:flex flex-col leading-tight">
                            <span class="font-label-md text-label-md truncate max-w-40">{{ auth()->user()->name }}</span>
                            <span class="font-body-sm text-body-sm text-secondary">{{ auth()->user()->role->label() }}</span>
                        </div>
                    </div>
                @endauth
            </div>
        </header>

        <main class="w-full pt-16 px-margin lg:px-gutter-desktop min-h-screen">
            <div class="flex flex-col w-full py-space-lg space-y-space-lg">
                @if (session('status'))
                    <div class="rounded-xl bg-primary-fixed text-on-primary-fixed-variant px-space-md py-space-sm font-label-md" role="status">{{ session('status') }}</div>
                @endif
                @if (session('warning'))
                    <div class="rounded-xl bg-tertiary-fixed text-on-tertiary-fixed px-space-md py-space-sm font-label-md" role="status">{{ session('warning') }}</div>
                @endif
                @if ($errors->has('plan_limit'))
                    <div class="rounded-xl bg-tertiary-fixed text-on-tertiary-fixed px-space-md py-space-sm font-label-md" role="alert">
                        {{ $errors->first('plan_limit') }}
                    </div>
                @endif
                @if ($errors->has('general'))
                    <div class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm font-label-md" role="alert">
                        {{ $errors->first('general') }}
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
