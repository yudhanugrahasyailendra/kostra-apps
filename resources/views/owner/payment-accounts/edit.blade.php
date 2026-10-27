@extends('layouts.owner')

@section('title', 'Ubah rekening')

@section('content')
    <x-panel :title="$account->type->label().': '.$account->label()">
        <p class="text-body-sm text-secondary mb-space-md">Rekening tidak dihapus karena dipakai pembayaran lama. Nonaktifkan bila tidak dipakai lagi.</p>
        <form method="POST" action="{{ route('owner.payment-accounts.update', $account) }}" class="flex flex-col gap-space-md">
            @csrf
            @method('PUT')
            @include('owner.payment-accounts._form', ['account' => $account])
            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.payment-accounts.index')" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>

    <x-panel title="Gambar QRIS">
        @if ($account->qrisImage)
            <img src="{{ $account->qrisImage->temporaryUrl() }}" alt="QRIS {{ $account->label() }}" class="max-h-64 rounded-lg mb-space-md">
        @endif
        <form method="POST" action="{{ route('owner.payment-accounts.qris', $account) }}" enctype="multipart/form-data" class="flex flex-col gap-space-md">
            @csrf
            <x-input name="file" type="file" accept=".jpg,.jpeg,.png" label="{{ $account->qrisImage ? 'Ganti' : 'Unggah' }} gambar QRIS statis (JPG atau PNG, maksimal 5 MB)" required />
            <div><x-button type="submit" variant="secondary">Unggah</x-button></div>
        </form>
    </x-panel>
@endsection
