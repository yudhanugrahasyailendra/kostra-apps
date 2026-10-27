@extends('layouts.guest')

@section('title', 'Lupa kata sandi')

@section('content')
    <x-panel>
        <h1 class="font-headline-lg text-headline-lg mb-space-xs">Lupa kata sandi</h1>
        <p class="text-secondary mb-space-md">Kami kirim tautan atur ulang ke email Anda. Tautan berlaku 60 menit.</p>

        @if (session('status'))
            <div class="mb-space-md rounded-xl bg-primary-fixed text-on-primary-fixed-variant px-space-md py-space-sm font-label-md" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-space-md">
            @csrf
            <x-input name="email" type="email" label="Email" autocomplete="email" required autofocus />
            <x-button type="submit" class="w-full">Kirim tautan</x-button>
        </form>

        <p class="mt-space-md text-body-sm"><a href="{{ route('login') }}" class="text-primary hover:underline">Kembali ke halaman masuk</a></p>
    </x-panel>
@endsection
