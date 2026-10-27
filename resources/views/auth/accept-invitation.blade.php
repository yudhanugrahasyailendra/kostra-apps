@extends('layouts.guest')

@section('title', 'Terima undangan')

@section('content')
    <x-panel>
        <h1 class="font-headline-lg text-headline-lg mb-space-xs">Terima undangan</h1>
        <p class="text-secondary mb-space-md">Buat akun staf untuk <strong>{{ $invitation->email ?? $invitation->phone }}</strong>.</p>

        <form method="POST" action="{{ route('invitation.accept', $token) }}" class="flex flex-col gap-space-md">
            @csrf
            <x-input name="name" label="Nama Anda" autocomplete="name" required autofocus />
            <x-input name="password" type="password" label="Kata sandi" autocomplete="new-password" hint="Minimal 8 karakter." required />
            <x-input name="password_confirmation" type="password" label="Ulangi kata sandi" autocomplete="new-password" required />
            <x-button type="submit" class="w-full">Bergabung</x-button>
        </form>
    </x-panel>
@endsection
