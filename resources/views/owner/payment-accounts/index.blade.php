@extends('layouts.owner')

@section('title', 'Rekening pembayaran')

@section('content')
    <x-panel title="Rekening dan QRIS">
        <p class="text-body-md text-secondary mb-space-md">Ditampilkan ke penghuni sebagai tujuan transfer. Uang masuk langsung ke rekening Anda; Kostra tidak menahan atau menyalurkannya.</p>

        @forelse ($accounts as $account)
            <div class="py-space-sm border-b border-outline-variant/40 last:border-0 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-label-lg">
                        {{ $account->type->label() }}: {{ $account->label() }}
                        @if ($account->is_default)<x-status-badge tone="ok">Utama</x-status-badge>@endif
                        @unless ($account->is_active)<x-status-badge tone="neutral">Nonaktif</x-status-badge>@endunless
                    </div>
                    <div class="font-body-sm text-secondary">
                        @if ($account->account_name) a.n. {{ $account->account_name }} · @endif
                        {{ $account->property?->name ?? 'Semua properti' }}
                        @if ($account->qrisImage) · gambar QRIS tersimpan @endif
                    </div>
                </div>
                <x-button :href="route('owner.payment-accounts.edit', $account)" variant="secondary">Ubah</x-button>
            </div>
        @empty
            <p class="text-on-surface-variant">Belum ada rekening.</p>
        @endforelse
    </x-panel>

    <x-panel title="Tambah rekening">
        <form method="POST" action="{{ route('owner.payment-accounts.store') }}" class="flex flex-col gap-space-md">
            @csrf
            @include('owner.payment-accounts._form', ['account' => null])
            <div><x-button type="submit">Simpan</x-button></div>
        </form>
    </x-panel>
@endsection
