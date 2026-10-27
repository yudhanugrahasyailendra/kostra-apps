@extends('layouts.owner')

@section('title', 'Properti')

@section('content')
    <div class="flex flex-wrap gap-space-sm">
        <x-button :href="route('owner.rooms.index')" variant="secondary">Kamar</x-button>
        <x-button :href="route('owner.room-types.index')" variant="secondary">Tipe kamar</x-button>
    </div>

    <x-panel title="Daftar properti">
        @forelse ($properties as $property)
            <div class="py-space-sm border-b border-outline-variant/40 last:border-0 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0 flex items-center gap-3">
                    @if ($property->photo)
                        <img src="{{ $property->photo->temporaryUrl() }}" alt="" class="w-12 h-12 rounded-lg object-cover" loading="lazy">
                    @endif
                    <div class="min-w-0">
                    <div class="font-label-lg">{{ $property->name }}
                        @unless ($property->is_active)
                            <x-status-badge tone="neutral">Nonaktif</x-status-badge>
                        @endunless
                    </div>
                    <div class="font-body-sm text-secondary">
                        {{ $property->city ?? '—' }} · {{ $property->rooms_count }} kamar
                        @if ($property->floors) · {{ $property->floors }} lantai @endif
                    </div>
                    </div>
                </div>
                @can('update', $property)
                    <div class="flex gap-2">
                        <x-button :href="route('owner.properties.edit', $property)" variant="secondary">Ubah</x-button>
                        <form method="POST" action="{{ route('owner.properties.destroy', $property) }}" onsubmit="return confirm('Hapus properti ini?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger">Hapus</x-button>
                        </form>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-on-surface-variant">Belum ada properti.</p>
        @endforelse
    </x-panel>

    @can('create', App\Models\Property::class)
        <x-panel title="Tambah properti">
            <form method="POST" action="{{ route('owner.properties.store') }}" class="flex flex-col gap-space-md">
                @csrf
                @include('owner.properties._form', ['property' => null])
                <div><x-button type="submit">Simpan</x-button></div>
            </form>
        </x-panel>
    @endcan
@endsection
