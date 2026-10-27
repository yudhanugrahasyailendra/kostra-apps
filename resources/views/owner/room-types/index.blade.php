@extends('layouts.owner')

@section('title', 'Tipe kamar')

@section('content')
    <div class="flex flex-wrap gap-space-sm">
        <x-button :href="route('owner.rooms.index')" variant="secondary">Kamar</x-button>
        <x-button :href="route('owner.properties.index')" variant="secondary">Properti</x-button>
    </div>

    <x-panel title="Daftar tipe kamar">
        @forelse ($types as $type)
            <div class="py-space-sm border-b border-outline-variant/40 last:border-0 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-label-lg">{{ $type->name }}
                        @unless ($type->is_active)
                            <x-status-badge tone="neutral">Nonaktif</x-status-badge>
                        @endunless
                    </div>
                    <div class="font-body-sm text-secondary">
                        <span class="angka">{{ App\Support\Rupiah::format($type->base_price) }}</span> /bulan ·
                        {{ $type->property?->name ?? 'Semua properti' }} · {{ $type->rooms_count }} kamar
                    </div>
                </div>
                @can('update', $type)
                    <div class="flex gap-2">
                        <x-button :href="route('owner.room-types.edit', $type)" variant="secondary">Ubah</x-button>
                        <form method="POST" action="{{ route('owner.room-types.destroy', $type) }}" onsubmit="return confirm('Hapus tipe ini?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger">Hapus</x-button>
                        </form>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-on-surface-variant">Belum ada tipe kamar.</p>
        @endforelse
    </x-panel>

    @can('create', App\Models\RoomType::class)
        <x-panel title="Tambah tipe kamar">
            <form method="POST" action="{{ route('owner.room-types.store') }}" class="flex flex-col gap-space-md">
                @csrf
                @include('owner.room-types._form', ['type' => null])
                <div><x-button type="submit">Simpan</x-button></div>
            </form>
        </x-panel>
    @endcan
@endsection
