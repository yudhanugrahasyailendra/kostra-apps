@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <h1 class="font-headline-lg text-headline-lg">Selamat datang kembali</h1>
    <p class="mt-1 mb-space-md text-secondary">Masuk untuk mengelola kos, memantau pembayaran, dan memeriksa bukti transfer.</p>

    @if (session('status'))
        <div class="mb-space-md rounded-xl bg-primary-fixed text-on-primary-fixed-variant px-space-md py-space-sm font-label-md" role="status">{{ session('status') }}</div>
    @endif

    {{-- Tab metode masuk. WhatsApp OTP menunggu integrasi pesan (Fase 9), jadi belum bisa dipilih. --}}
    <div role="tablist" aria-label="Metode masuk" class="flex border-b border-outline-variant/60 mb-space-md">
        <span role="tab" id="tab-password" aria-selected="true" aria-controls="panel-password"
              class="flex items-center gap-2 px-4 py-2 -mb-px border-b-2 border-primary text-primary font-label-lg text-label-lg">
            <span class="material-symbols-outlined text-base" aria-hidden="true">lock</span>
            Kata Sandi
        </span>
        <span role="tab" id="tab-whatsapp" aria-selected="false" aria-disabled="true"
              class="flex items-center gap-2 px-4 py-2 -mb-px border-b-2 border-transparent text-on-surface-variant opacity-60 cursor-not-allowed font-label-lg text-label-lg">
            <span class="material-symbols-outlined text-base" aria-hidden="true">chat</span>
            WhatsApp OTP
            <span class="badge badge-neutral">Segera</span>
        </span>
    </div>

    <div role="tabpanel" id="panel-password" aria-labelledby="tab-password">
        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-space-md">
            @csrf
            <x-input name="login" label="Email atau nomor WhatsApp" autocomplete="username" hint="Format: 08xx atau nama@email.com" required autofocus />
            <x-input name="password" type="password" label="Kata sandi" autocomplete="current-password" required />

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-body-sm">
                    <input type="checkbox" name="remember" value="1" class="rounded border-outline-variant">
                    Ingat saya di perangkat ini
                </label>
                <a href="{{ route('password.request') }}" class="text-body-sm text-primary hover:underline">Lupa kata sandi?</a>
            </div>

            <x-button type="submit" class="w-full">Masuk ke Dasbor</x-button>
        </form>
    </div>

    <p class="mt-space-lg text-body-md text-center text-secondary">
        Belum punya akun pengelola kos?
        <a href="{{ route('register') }}" class="text-primary font-label-lg hover:underline">Daftar gratis</a>
    </p>
@endsection
