@extends('layouts.owner')

@section('title', 'Ringkasan')

@section('content')
    @forelse ($floorPlan as $propertyName => $floors)
        <x-panel :title="'Denah '.$propertyName">
            <div class="flex flex-col gap-space-md">
                @foreach ($floors as $floor => $rooms)
                    <div>
                        <h3 class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-space-sm">
                            {{ $floor === 0 ? 'Tanpa lantai' : 'Lantai '.$floor }}
                        </h3>
                        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-space-sm">
                            @foreach ($rooms as $room)
                                <x-room-plate
                                    :number="$room->number"
                                    :label="$room->status->label()"
                                    :tone="$room->status->tone()"
                                    :sub="$room->roomType?->name"
                                    :href="route('owner.rooms.index', ['property_id' => $room->property_id])" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    @empty
        <x-panel title="Selamat datang, {{ auth()->user()->name }}">
            <p class="text-on-surface-variant mb-space-md">Kos Anda masih kosong. Mulai dengan menambahkan properti dan kamar.</p>
            @can('create', App\Models\Property::class)
                <x-button :href="route('owner.properties.index')">Tambah properti</x-button>
            @endcan
        </x-panel>
    @endforelse
@endsection
