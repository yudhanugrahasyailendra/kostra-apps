@extends('layouts.owner')

@section('title', 'Ubah tipe kamar')

@section('content')
    <x-panel :title="$type->name">
        <p class="text-secondary mb-space-md">Berlaku untuk: {{ $type->property?->name ?? 'Semua properti' }}</p>
        <form method="POST" action="{{ route('owner.room-types.update', $type) }}" class="flex flex-col gap-space-md">
            @csrf
            @method('PUT')
            @include('owner.room-types._form', ['type' => $type, 'properties' => collect()])
            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.room-types.index')" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>
@endsection
