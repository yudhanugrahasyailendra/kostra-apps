@extends('layouts.owner')

@section('title', 'Edit akun')

@section('content')
    <form method="POST" action="{{ route('account.update') }}" class="flex flex-col gap-space-lg max-w-2xl">
        @csrf
        @method('PUT')

        <x-panel title="Data akun">
            <div class="flex flex-col gap-space-md">
                <x-input name="name" label="Nama" :value="$user->name" autocomplete="name" required />
                <x-input name="email" type="email" label="Email" :value="$user->email" autocomplete="email" />
                <x-input name="phone" label="Nomor WhatsApp" :value="$user->phone" autocomplete="tel" hint="Isi email atau nomor WhatsApp, minimal salah satu. Dipakai untuk masuk." />
            </div>
        </x-panel>

        <x-panel title="Ganti kata sandi">
            <div class="flex flex-col gap-space-md">
                <p class="font-body-sm text-body-sm text-secondary">Kosongkan bila tidak ingin mengganti kata sandi.</p>
                <x-input name="current_password" type="password" label="Kata sandi saat ini" autocomplete="current-password" />
                <x-input name="password" type="password" label="Kata sandi baru" autocomplete="new-password" hint="Minimal 8 karakter." />
                <x-input name="password_confirmation" type="password" label="Ulangi kata sandi baru" autocomplete="new-password" />
            </div>
        </x-panel>

        <div class="flex gap-2">
            <x-button type="submit">Simpan</x-button>
            <x-button :href="auth()->user()->isSuperAdmin() ? '/admin' : route('owner.dashboard')" variant="secondary">Kembali</x-button>
        </div>
    </form>
@endsection
