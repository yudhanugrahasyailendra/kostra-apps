@extends('layouts.guest')

@section('title', 'Daftar')

@section('content')
    <x-panel>
        <h1 class="font-headline-lg text-headline-lg mb-space-xs">Daftar</h1>
        <p class="text-secondary mb-space-md">Mulai dengan paket Gratis. Isi email atau nomor WhatsApp (salah satu cukup).</p>

        <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-space-md">
            @csrf
            <x-input name="business_name" label="Nama kos" autocomplete="organization" required autofocus />
            <x-input name="name" label="Nama Anda" autocomplete="name" required />
            <x-input name="email" type="email" label="Email" autocomplete="email" />
            <x-input name="phone" type="tel" label="Nomor WhatsApp" autocomplete="tel" hint="Contoh: 0812 3456 7890" />
            <x-input name="password" type="password" label="Kata sandi" autocomplete="new-password" hint="Minimal 8 karakter." required />
            <x-input name="password_confirmation" type="password" label="Ulangi kata sandi" autocomplete="new-password" required />

            @error('email')
            @enderror

            <x-button type="submit" class="w-full">Buat akun</x-button>
        </form>

        <p class="mt-space-md text-body-sm">Sudah punya akun? <a href="{{ route('login') }}" class="text-primary hover:underline">Masuk</a></p>
    </x-panel>
@endsection
