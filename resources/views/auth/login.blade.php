@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <x-panel>
        <h1 class="font-headline-lg text-headline-lg mb-space-xs">Masuk</h1>
        <p class="text-secondary mb-space-md">Gunakan email atau nomor WhatsApp Anda.</p>

        @if (session('status'))
            <div class="mb-space-md rounded-xl bg-primary-fixed text-on-primary-fixed-variant px-space-md py-space-sm font-label-md" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-space-md">
            @csrf
            <x-input name="login" label="Email atau nomor WhatsApp" autocomplete="username" required autofocus />
            <x-input name="password" type="password" label="Kata sandi" autocomplete="current-password" required />

            <label class="flex items-center gap-2 text-body-sm">
                <input type="checkbox" name="remember" value="1" class="rounded border-outline-variant">
                Ingat saya
            </label>

            <x-button type="submit" class="w-full">Masuk</x-button>
        </form>

        <div class="mt-space-md flex justify-between text-body-sm">
            <a href="{{ route('password.request') }}" class="text-primary hover:underline">Lupa kata sandi?</a>
            <a href="{{ route('register') }}" class="text-primary hover:underline">Daftar</a>
        </div>
    </x-panel>
@endsection
