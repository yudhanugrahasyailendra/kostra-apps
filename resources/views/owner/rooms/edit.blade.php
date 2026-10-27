@extends('layouts.owner')

@section('title', 'Ubah kamar')

@section('content')
    <x-panel :title="'Kamar '.$room->number.' · '.$room->property->name">
        <form method="POST" action="{{ route('owner.rooms.update', $room) }}" class="flex flex-col gap-space-md">
            @csrf
            @method('PUT')
            <x-input name="number" label="Nomor kamar" :value="$room->number" required />
            <x-select name="room_type_id" label="Tipe" placeholder="Tanpa tipe">
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected(old('room_type_id', $room->room_type_id) == $type->id)>{{ $type->name }}</option>
                @endforeach
            </x-select>
            <div class="grid grid-cols-2 gap-space-md">
                <x-input name="floor" type="number" min="0" max="99" label="Lantai" :value="$room->floor" />
                <x-input name="price" inputmode="numeric" label="Harga per bulan (Rp)" :value="number_format($room->price, 0, ',', '.')" required />
            </div>

            @if ($room->isOccupied())
                <div>
                    <div class="font-label-md mb-1">Status</div>
                    <x-status-badge :tone="$room->status->tone()">{{ $room->status->label() }}</x-status-badge>
                    <p class="text-body-sm text-secondary mt-1">Status terisi ditentukan oleh kontrak aktif, bukan diatur manual.</p>
                </div>
            @else
                <x-select name="status" label="Status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $room->status->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-select>
            @endif

            <x-input name="facilities" label="Fasilitas" :value="implode(', ', $room->facilities ?? [])" hint="Pisahkan dengan koma. Kosong = ikut fasilitas tipe." />
            <x-input name="notes" label="Catatan" :value="$room->notes" />

            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.rooms.index', ['property_id' => $room->property_id])" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>

    @unless ($room->isOccupied())
        <x-panel title="Hapus kamar">
            <form method="POST" action="{{ route('owner.rooms.destroy', $room) }}" onsubmit="return confirm('Hapus kamar ini?')">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger">Hapus kamar</x-button>
            </form>
        </x-panel>
    @endunless
@endsection
