@extends('layouts.guest')

@section('title', 'Atur ulang kata sandi')

@section('content')
    <x-panel>
        <h1 class="font-headline-lg text-headline-lg mb-space-md">Atur ulang kata sandi</h1>

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-space-md">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-input name="email" type="email" label="Email" :value="$email" autocomplete="email" required />
            <x-input name="password" type="password" label="Kata sandi baru" autocomplete="new-password" hint="Minimal 8 karakter." required autofocus />
            <x-input name="password_confirmation" type="password" label="Ulangi kata sandi baru" autocomplete="new-password" required />
            <x-button type="submit" class="w-full">Simpan kata sandi</x-button>
        </form>
    </x-panel>
@endsection
