@extends('layouts.owner')

@section('title', 'Pengaturan')

@section('content')
    <x-panel title="Pengaturan">
        @can('viewAny', App\Models\User::class)
            <a href="{{ route('owner.staff.index') }}" class="flex items-center gap-3 text-primary hover:underline">
                <span class="material-symbols-outlined" aria-hidden="true">group</span>
                Staf dan undangan
            </a>
        @else
            <p class="text-on-surface-variant">Belum ada pengaturan yang tersedia untuk akun Anda.</p>
        @endcan
    </x-panel>
@endsection
