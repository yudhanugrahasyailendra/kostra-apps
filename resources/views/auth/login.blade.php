@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary-fixed text-on-primary-fixed-variant font-label-sm text-label-sm uppercase tracking-wider mb-space-sm"><span class="material-symbols-outlined text-sm" aria-hidden="true">shield_person</span> Portal pemilik &amp; staf</span>
    <h1 class="font-headline-lg-mobile text-headline-lg-mobile lg:font-headline-xl lg:text-headline-xl">Selamat datang kembali</h1>
    <p class="mt-1 mb-space-md text-secondary">Masuk untuk mengelola kos, memantau pembayaran, dan memeriksa bukti transfer.</p>

    @if (session('status'))
        <div class="mb-space-md rounded-xl bg-primary-fixed text-on-primary-fixed-variant px-space-md py-space-sm font-label-md" role="status">{{ session('status') }}</div>
    @endif

    {{-- Tab metode masuk. WhatsApp OTP menunggu integrasi pesan (Fase 9), jadi belum bisa dipilih. --}}
    <div role="tablist" aria-label="Metode masuk" class="flex gap-1 p-1 rounded-xl bg-surface-container-low mb-space-md">
        <span role="tab" id="tab-password" aria-selected="true" aria-controls="panel-password"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-surface-container-lowest shadow-sm text-primary font-label-lg text-label-lg">
            <span class="material-symbols-outlined text-base" aria-hidden="true">lock</span>
            Kata Sandi
        </span>
        <span role="tab" id="tab-whatsapp" aria-selected="false" aria-disabled="true"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-on-surface-variant opacity-60 cursor-not-allowed font-label-lg text-label-lg">
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
                    <input type="checkbox" name="remember" value="1" class="rounded text-primary focus:ring-primary h-4 w-4">
                    Ingat saya di perangkat ini
                </label>
                <a href="{{ route('password.request') }}" class="text-body-sm text-primary hover:underline">Lupa kata sandi?</a>
            </div>

            <x-button type="submit" class="w-full">Masuk ke Dasbor <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></x-button>
        </form>
    </div>

    <p class="mt-space-lg p-space-sm rounded-xl bg-surface-container-low text-body-md text-center text-secondary">
        Belum punya akun pengelola kos?
        <a href="{{ route('register') }}" class="text-primary font-label-lg hover:underline">Daftar gratis</a>
    </p>
@endsection
