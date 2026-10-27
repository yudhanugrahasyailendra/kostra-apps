@extends('layouts.owner')

@section('title', 'Pengaturan')

@section('content')
    <x-panel title="Pengaturan">
        <a href="{{ route('owner.settings.billing') }}" class="flex items-center gap-3 text-primary hover:underline mb-space-sm">
            <span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>
            Penagihan (tanggal terbit, jatuh tempo, pro rata)
        </a>
        @can('viewAny', App\Models\User::class)
            <a href="{{ route('owner.staff.index') }}" class="flex items-center gap-3 text-primary hover:underline">
                <span class="material-symbols-outlined" aria-hidden="true">group</span>
                Staf dan undangan
            </a>
        @endcan
    </x-panel>
@endsection
