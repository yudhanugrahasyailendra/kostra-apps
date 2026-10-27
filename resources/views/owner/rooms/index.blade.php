@extends('layouts.owner')

@section('title', 'Properti & Kamar')

@section('content')
    @php
        $total = $counts->sum();
        $grouped = $rooms->getCollection()
            ->groupBy(fn ($room) => $room->property->name)
            ->map(fn ($group) => $group->groupBy(fn ($room) => $room->floor ?? 0));
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-space-sm">
        <div class="flex flex-wrap gap-space-sm">
            <x-button :href="route('owner.properties.index')" variant="secondary">Properti</x-button>
            <x-button :href="route('owner.room-types.index')" variant="secondary">Tipe kamar</x-button>
        </div>
        @can('create', App\Models\Room::class)
            <div class="flex flex-wrap gap-space-sm">
                <x-button type="button" variant="secondary" data-modal-open="modal-import">Impor CSV</x-button>
                <x-button type="button" variant="secondary" data-modal-open="modal-bulk">Tambah massal</x-button>
                <x-button type="button" data-modal-open="modal-room">Tambah kamar</x-button>
            </div>
        @endcan
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md">
        @foreach ([
            ['Total kamar', $total, 'neutral'],
            ['Terisi', $counts[App\Enums\RoomStatus::Occupied->value] ?? 0, 'ok'],
            ['Kosong', $counts[App\Enums\RoomStatus::Vacant->value] ?? 0, 'neutral'],
            ['Perawatan', $counts[App\Enums\RoomStatus::Maintenance->value] ?? 0, 'maintenance'],
        ] as [$label, $value, $tone])
            <div class="panel p-space-md">
                <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">{{ $label }}</div>
                <div class="font-currency-stat text-currency-stat angka">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <p class="text-body-sm text-secondary">
        Paket {{ $planName }}:
        @if ($maxRooms === null)
            kamar tanpa batas.
        @else
            <span class="angka">{{ $maxRooms - $remainingRooms }}</span> dari <span class="angka">{{ $maxRooms }}</span> kamar terpakai.
        @endif
    </p>

    @if (session('import_failures'))
        <x-panel title="Baris impor yang gagal">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-body-sm">
                    <thead class="bg-surface-container-low text-secondary font-label-sm uppercase tracking-wider">
                        <tr><th class="p-2">Baris</th><th class="p-2">Nomor</th><th class="p-2">Alasan</th></tr>
                    </thead>
                    <tbody>
                        @foreach (session('import_failures') as $failure)
                            <tr class="border-t border-outline-variant/40">
                                <td class="p-2 angka">{{ $failure['line'] }}</td>
                                <td class="p-2">{{ $failure['number'] ?: '—' }}</td>
                                <td class="p-2">{{ $failure['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

    <form method="GET" action="{{ route('owner.rooms.index') }}" class="panel p-space-md grid grid-cols-2 lg:grid-cols-5 gap-space-md items-end">
        <x-select name="property_id" label="Properti" placeholder="Semua properti">
            @foreach ($properties as $property)
                <option value="{{ $property->id }}" @selected(($filters['property_id'] ?? null) == $property->id)>{{ $property->name }}</option>
            @endforeach
        </x-select>
        <x-select name="status" label="Status" placeholder="Semua status">
            @foreach (App\Enums\RoomStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-select>
        <x-select name="room_type_id" label="Tipe" placeholder="Semua tipe">
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected(($filters['room_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
            @endforeach
        </x-select>
        <x-input name="floor" type="number" min="0" max="99" label="Lantai" :value="$filters['floor'] ?? null" />
        <div class="flex gap-2">
            <x-button type="submit">Terapkan</x-button>
            <x-button :href="route('owner.rooms.index')" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    @forelse ($grouped as $propertyName => $floors)
        <x-panel :title="$propertyName">
            <div class="flex flex-col gap-space-md">
                @foreach ($floors as $floor => $floorRooms)
                    <div>
                        <h3 class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-space-sm">
                            {{ $floor === 0 ? 'Tanpa lantai' : 'Lantai '.$floor }} · {{ $floorRooms->count() }} kamar
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-space-sm">
                            @foreach ($floorRooms as $room)
                                @can('update', $room)
                                    <x-room-plate :number="$room->number" :label="$room->status->label()" :tone="$room->status->tone()"
                                                  :sub="$room->roomType?->name" :href="route('owner.rooms.edit', $room)">
                                        <div class="font-body-sm text-body-sm angka">{{ App\Support\Rupiah::format($room->price) }}</div>
                                    </x-room-plate>
                                @else
                                    <x-room-plate :number="$room->number" :label="$room->status->label()" :tone="$room->status->tone()"
                                                  :sub="$room->roomType?->name">
                                        <div class="font-body-sm text-body-sm angka">{{ App\Support\Rupiah::format($room->price) }}</div>
                                    </x-room-plate>
                                @endcan
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    @empty
        <x-panel>
            <p class="text-on-surface-variant">
                @if ($properties->isEmpty())
                    Belum ada properti. <a class="text-primary hover:underline" href="{{ route('owner.properties.index') }}">Tambah properti</a> lebih dulu, lalu isi kamarnya.
                @else
                    Tidak ada kamar yang cocok dengan filter ini.
                @endif
            </p>
        </x-panel>
    @endforelse

    {{ $rooms->links() }}

    @can('create', App\Models\Room::class)
        <x-modal id="modal-room" title="Tambah kamar">
            <form method="POST" action="{{ route('owner.rooms.store') }}" class="flex flex-col gap-space-md">
                @csrf
                <input type="hidden" name="_modal" value="modal-room">
                <x-select name="property_id" label="Properti" placeholder="Pilih properti" required>
                    @foreach ($properties->where('is_active', true) as $property)
                        <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>{{ $property->name }}</option>
                    @endforeach
                </x-select>
                <x-input name="number" label="Nomor kamar" required />
                <x-select name="room_type_id" label="Tipe" placeholder="Tanpa tipe">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(old('room_type_id') == $type->id)>{{ $type->name }} · {{ App\Support\Rupiah::format($type->base_price) }}</option>
                    @endforeach
                </x-select>
                <div class="grid grid-cols-2 gap-space-md">
                    <x-input name="floor" type="number" min="0" max="99" label="Lantai" />
                    <x-input name="price" inputmode="numeric" label="Harga per bulan (Rp)" hint="Kosongkan untuk memakai harga tipe." />
                </div>
                <x-select name="status" label="Status">
                    @foreach (App\Enums\RoomStatus::manual() as $status)
                        <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-select>
                <x-input name="facilities" label="Fasilitas" hint="Pisahkan dengan koma." />
                <x-input name="notes" label="Catatan" />
                <div><x-button type="submit">Simpan</x-button></div>
            </form>
        </x-modal>

        <x-modal id="modal-bulk" title="Tambah kamar massal">
            <form method="POST" action="{{ route('owner.rooms.bulk') }}" class="flex flex-col gap-space-md">
                @csrf
                <input type="hidden" name="_modal" value="modal-bulk">
                <x-select name="property_id" label="Properti" placeholder="Pilih properti" required>
                    @foreach ($properties->where('is_active', true) as $property)
                        <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>{{ $property->name }}</option>
                    @endforeach
                </x-select>
                <div class="grid grid-cols-3 gap-space-md">
                    <x-input name="prefix" label="Awalan" hint="Opsional, mis. A-" />
                    <x-input name="from" type="number" min="0" label="Dari nomor" required />
                    <x-input name="to" type="number" min="0" label="Sampai nomor" required />
                </div>
                <x-select name="room_type_id" label="Tipe" placeholder="Tanpa tipe">
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(old('room_type_id') == $type->id)>{{ $type->name }} · {{ App\Support\Rupiah::format($type->base_price) }}</option>
                    @endforeach
                </x-select>
                <div class="grid grid-cols-2 gap-space-md">
                    <x-input name="floor" type="number" min="0" max="99" label="Lantai" />
                    <x-input name="price" inputmode="numeric" label="Harga per bulan (Rp)" hint="Kosongkan untuk memakai harga tipe." />
                </div>
                <x-input name="facilities" label="Fasilitas" hint="Pisahkan dengan koma." />
                <p class="text-body-sm text-secondary">Maksimal {{ App\Actions\Rooms\CreateRoomsInBulk::MAX_PER_BATCH }} kamar per langkah. Bila ada satu nomor bentrok, tidak ada kamar yang dibuat.</p>
                <div><x-button type="submit">Buat kamar</x-button></div>
            </form>
        </x-modal>

        <x-modal id="modal-import" title="Impor kamar dari CSV">
            <form method="POST" action="{{ route('owner.rooms.import') }}" enctype="multipart/form-data" class="flex flex-col gap-space-md">
                @csrf
                <input type="hidden" name="_modal" value="modal-import">
                <x-select name="property_id" label="Properti" placeholder="Pilih properti" required>
                    @foreach ($properties->where('is_active', true) as $property)
                        <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>{{ $property->name }}</option>
                    @endforeach
                </x-select>
                <x-input name="file" type="file" accept=".csv,text/csv" label="Berkas CSV" required />
                <p class="text-body-sm text-secondary">
                    Baris pertama berisi nama kolom: <code>nomor</code> (wajib), <code>lantai</code>, <code>tipe</code>, <code>harga</code>, <code>fasilitas</code> (pisahkan dengan <code>|</code>).
                    Pemisah koma atau titik koma. Maksimal 5 MB. Baris yang valid tetap masuk; baris yang gagal ditampilkan beserta alasannya.
                </p>
                <div><x-button type="submit">Impor</x-button></div>
            </form>
        </x-modal>
    @endcan

    @if (old('_modal'))
        <script>document.getElementById(@json(old('_modal')))?.showModal();</script>
    @endif
@endsection
