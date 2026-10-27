<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') · Kostra</title>
    @include('layouts.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface min-h-screen">
    <div class="min-h-screen grid lg:grid-cols-2">
        {{-- Kolom formulir --}}
        <main class="flex flex-col justify-between px-margin py-space-lg lg:px-margin-desktop">
            <header class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-3xl" aria-hidden="true">apartment</span>
                <div class="flex flex-col leading-tight">
                    <span class="font-headline-md text-headline-md text-primary tracking-tight">Kostra</span>
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Manajemen Kos</span>
                </div>
            </header>

            <div class="w-full max-w-md mx-auto py-space-xl">
                @yield('content')
            </div>

            <footer class="font-body-sm text-body-sm text-secondary">
                &copy; {{ date('Y') }} Kostra. Pembayaran dicatat di luar sistem; Kostra tidak menahan uang sewa Anda.
            </footer>
        </main>

        {{-- Kolom penjelasan produk: hanya di layar lebar --}}
        <aside class="hidden lg:flex flex-col justify-center gap-space-lg bg-primary-container text-on-primary px-margin-desktop py-space-xl" aria-label="Tentang Kostra">
            <div>
                <h2 class="font-headline-xl text-headline-xl">Kelola kos tanpa rekap manual.</h2>
                <p class="mt-space-sm font-body-lg text-body-lg text-on-primary-container">
                    Tagihan, pembayaran, dan pengingat penghuni dalam satu tempat, untuk kos 5 sampai 50 kamar.
                </p>
            </div>

            <ul class="flex flex-col gap-space-md">
                @foreach ([
                    ['receipt_long', 'Tagihan bulanan terbit otomatis', 'Tanpa membuat tagihan satu per satu setiap bulan.'],
                    ['verified', 'Pembayaran dan kuitansi tercatat rapi', 'Bukti transfer penghuni diperiksa, lalu kuitansi dibuat otomatis.'],
                    ['link', 'Portal penghuni tanpa akun', 'Penghuni membuka tagihannya lewat tautan di WhatsApp.'],
                    ['percent', 'Tanpa komisi', 'Uang sewa langsung ke rekening Anda; Kostra hanya mencatat.'],
                ] as [$icon, $title, $text])
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5" aria-hidden="true">{{ $icon }}</span>
                        <div>
                            <div class="font-label-lg text-label-lg">{{ $title }}</div>
                            <div class="font-body-md text-body-md text-on-primary-container">{{ $text }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
</body>
</html>
