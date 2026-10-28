<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') · Kostra</title>
    @include('layouts.fonts')
    @include('layouts.theme')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface min-h-screen flex flex-col">
    <header class="bg-surface-container-lowest/90 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
        <div class="max-w-6xl mx-auto h-16 px-margin lg:px-0 flex items-center justify-between">
            <a href="{{ route('login') }}" class="flex items-center gap-space-sm">
                <span class="w-9 h-9 rounded-lg bg-primary text-on-primary flex items-center justify-center" aria-hidden="true">
                    <span class="material-symbols-outlined text-xl">apartment</span>
                </span>
                <span class="flex flex-col leading-tight">
                    <span class="font-headline-md text-headline-md text-primary tracking-tight">Kostra</span>
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Manajemen Kos</span>
                </span>
            </a>
            <nav class="flex items-center gap-space-sm" aria-label="Akun">
                <x-theme-toggle />
                <a href="{{ route('login') }}" @if (request()->routeIs('login')) aria-current="page" @endif class="px-4 py-2 rounded-xl font-label-md text-label-md {{ request()->routeIs('login') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container' }}">Masuk</a>
                <a href="{{ route('register') }}" @if (request()->routeIs('register')) aria-current="page" @endif class="px-4 py-2 rounded-xl font-label-md text-label-md {{ request()->routeIs('register') ? 'bg-secondary-container text-on-secondary-container' : 'text-on-surface-variant hover:bg-surface-container' }}">Daftar</a>
            </nav>
        </div>
    </header>

    <main class="flex-1 px-margin lg:px-0 py-space-lg lg:py-space-xl">
        <div class="max-w-6xl mx-auto grid lg:grid-cols-[1.15fr_1fr] rounded-3xl overflow-hidden bg-surface-container-lowest shadow-sm">
            <div class="px-space-lg py-space-xl lg:px-16 lg:py-14 flex flex-col justify-center">
                <div class="w-full max-w-md mx-auto lg:mx-0">
                    @yield('content')
                </div>
            </div>

            {{-- Panel penjelasan produk: hanya di layar lebar. Hanya fitur yang benar-benar ada. --}}
            <aside class="hidden lg:flex flex-col justify-center gap-space-lg bg-linear-to-br from-primary-container to-primary text-on-primary p-space-xl lg:p-12" aria-label="Tentang Kostra">
                <div>
                    <h2 class="font-headline-xl text-headline-xl">Kelola kos tanpa rekap manual.</h2>
                    <p class="mt-space-sm font-body-lg text-body-lg text-on-primary-container">
                        Tagihan, pembayaran, dan pengingat penghuni dalam satu tempat, untuk kos 5 sampai 50 kamar.
                    </p>
                </div>

                <ul class="flex flex-col gap-space-sm">
                    @foreach ([
                        ['receipt_long', 'Tagihan bulanan terbit otomatis', 'Tanpa membuat tagihan satu per satu setiap bulan.'],
                        ['verified', 'Pembayaran dan kuitansi tercatat rapi', 'Bukti transfer penghuni diperiksa, lalu kuitansi dibuat.'],
                        ['link', 'Portal penghuni tanpa akun', 'Penghuni membuka tagihannya lewat tautan di WhatsApp.'],
                        ['percent', 'Tanpa komisi', 'Uang sewa langsung ke rekening Anda; Kostra hanya mencatat.'],
                    ] as [$icon, $title, $text])
                        <li class="flex items-start gap-3 p-space-sm rounded-xl bg-on-primary/10 backdrop-blur-sm">
                            <span class="w-10 h-10 rounded-lg bg-on-primary/15 flex items-center justify-center shrink-0" aria-hidden="true">
                                <span class="material-symbols-outlined">{{ $icon }}</span>
                            </span>
                            <div>
                                <div class="font-label-lg text-label-lg">{{ $title }}</div>
                                <div class="font-body-sm text-body-sm text-on-primary-container">{{ $text }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </main>

    <footer class="px-margin py-space-md text-center font-body-sm text-body-sm text-secondary">
        &copy; {{ date('Y') }} Kostra. Pembayaran dicatat di luar sistem; Kostra tidak menahan uang sewa Anda.
    </footer>
</body>
</html>
