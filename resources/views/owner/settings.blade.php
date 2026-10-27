@extends('layouts.owner')

@section('title', 'Pengaturan')

@section('content')
    <x-panel title="Pengaturan">
        <a href="{{ route('owner.settings.billing') }}" class="flex items-center gap-3 text-primary hover:underline mb-space-sm">
            <span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>
            Penagihan (tanggal terbit, jatuh tempo, pro rata)
        </a>
        <a href="{{ route('owner.payment-accounts.index') }}" class="flex items-center gap-3 text-primary hover:underline mb-space-sm">
            <span class="material-symbols-outlined" aria-hidden="true">account_balance</span>
            Rekening pembayaran dan QRIS
        </a>
        @can('export-data')
            <a href="{{ route('owner.export.index') }}" class="flex items-center gap-3 text-primary hover:underline mb-space-sm">
                <span class="material-symbols-outlined" aria-hidden="true">download</span>
                Ekspor seluruh data kos
            </a>
        @endcan
        @can('viewAny', App\Models\User::class)
            <a href="{{ route('owner.staff.index') }}" class="flex items-center gap-3 text-primary hover:underline">
                <span class="material-symbols-outlined" aria-hidden="true">group</span>
                Staf dan undangan
            </a>
        @endcan
    </x-panel>
@endsection
