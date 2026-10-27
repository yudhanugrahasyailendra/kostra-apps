@extends('layouts.owner')

@section('title', 'Properti & Kamar')

@section('content')
    @php
        $total = $counts->sum();
        $grouped = $rooms->getCollection()
            ->groupBy(fn ($room) => $room->property->name)
            ->map(fn ($group) => $group->groupBy(fn ($room) => $room->floor ?? 0));
    @endphp


    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-space-md">
        <div>
            <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Inventaris kamar</p>
            <h2 class="font-headline-lg-mobile text-headline-lg-mobile lg:font-headline-lg lg:text-headline-lg">Manajemen Properti &amp; Kamar</h2>
        </div>
        <div class="flex flex-wrap items-center gap-space-sm">
            <x-button :href="route('owner.properties.index')" variant="secondary"><span class="material-symbols-outlined text-base" aria-hidden="true">apartment</span> Properti</x-button>
            <x-button :href="route('owner.room-types.index')" variant="secondary"><span class="material-symbols-outlined text-base" aria-hidden="true">category</span> Tipe kamar</x-button>
            @can('create', App\Models\Room::class)
                <x-button type="button" variant="secondary" data-modal-open="modal-import"><span class="material-symbols-outlined text-base" aria-hidden="true">upload</span> Impor CSV</x-button>
                <x-button type="button" variant="secondary" data-modal-open="modal-bulk"><span class="material-symbols-outlined text-base" aria-hidden="true">domain_add</span> Tambah massal</x-button>
                <x-button type="button" data-modal-open="modal-room"><span class="material-symbols-outlined text-base" aria-hidden="true">add</span> Tambah kamar</x-button>
            @endcan
        </div>
    </div>

    @php
        $occupied = $counts[App\Enums\RoomStatus::Occupied->value] ?? 0;
        $percent = $total > 0 ? round($occupied / $total * 100, 1) : 0;
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-space-md">
        @foreach ([
            ['Total kamar', $total, 'meeting_room', 'text-on-surface'],
            ['Terisi', $occupied, 'how_to_reg', 'text-primary'],
            ['Kosong', $counts[App\Enums\RoomStatus::Vacant->value] ?? 0, 'bed', 'text-tertiary'],
            ['Perawatan', $counts[App\Enums\RoomStatus::Maintenance->value] ?? 0, 'build', 'text-on-surface'],
        ] as [$label, $value, $icon, $color])
            <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="kpi-label">{{ $label }}</span>
                    <span class="material-symbols-outlined text-secondary" aria-hidden="true">{{ $icon }}</span>
                </div>
                <div class="font-currency-stat text-currency-stat {{ $color }} angka">{{ $value }}</div>
            </div>
        @endforeach
        <div class="col-span-2 lg:col-span-1 p-space-md rounded-xl bg-primary text-on-primary shadow-sm flex flex-col justify-between">
            <span class="font-label-sm text-label-sm uppercase tracking-wider text-on-primary-container">Tingkat hunian</span>
            <div class="font-currency-stat text-currency-stat angka">{{ str_replace('.', ',', (string) $percent) }}%</div>
            <span class="font-label-md text-label-md text-on-primary-container angka">{{ $occupied }} dari {{ $total }} kamar terisi</span>
        </div>
    </div>

    <p class="font-body-sm text-body-sm text-secondary">
        Paket {{ $planName }}:
        @if ($maxRooms === null)
            kamar tanpa batas.
        @else
            <span class="angka">{{ $maxRooms - $remainingRooms }}</span> dari <span class="angka">{{ $maxRooms }}</span> kamar terpakai.
        @endif
    </p>

    @if (session('import_failures'))
        <x-panel title="Baris impor yang gagal">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Baris</th><th>Nomor</th><th>Alasan</th></tr>
                    </thead>
                    <tbody>
                        @foreach (session('import_failures') as $failure)
                            <tr>
                                <td class="angka">{{ $failure['line'] }}</td>
                                <td>{{ $failure['number'] ?: '—' }}</td>
                                <td>{{ $failure['reason'] }}</td>
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
        <section class="flex flex-col gap-space-md">
            <h2 class="panel-title">{{ $propertyName }}</h2>
            @foreach ($floors as $floor => $floorRooms)
                <div class="flex flex-col gap-space-sm">
                    <div class="flex items-center gap-space-sm">
                        <span class="w-9 h-9 rounded-lg bg-primary-fixed text-on-primary-fixed-variant flex items-center justify-center font-label-lg text-label-lg angka" aria-hidden="true">{{ $floor === 0 ? '–' : 'L'.$floor }}</span>
                        <div>
                            <h3 class="font-headline-md text-headline-md">{{ $floor === 0 ? 'Tanpa lantai' : 'Lantai '.$floor }}</h3>
                            <p class="font-body-sm text-body-sm text-secondary angka">{{ $floorRooms->count() }} unit · {{ $floorRooms->where('status', App\Enums\RoomStatus::Occupied)->count() }} terisi · {{ $floorRooms->where('status', App\Enums\RoomStatus::Vacant)->count() }} kosong</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-space-md">
                        @foreach ($floorRooms as $room)
                            @php($resident = $room->activeLease?->primaryResident?->name)
                            <div class="group p-space-md rounded-xl bg-surface-container-lowest shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between gap-space-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="font-headline-lg text-headline-lg angka">{{ $room->number }}</div>
                                        <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">{{ $room->roomType?->name ?? 'Tanpa tipe' }}</div>
                                    </div>
                                    <x-status-badge :tone="$room->status->tone()">{{ $room->status->label() }}</x-status-badge>
                                </div>
                                <div class="font-body-sm text-body-sm min-h-10">
                                    @if ($resident)
                                        <div class="font-label-md text-label-md text-on-surface">{{ $resident }}</div>
                                        @if ($room->activeLease?->end_date)
                                            <div class="text-secondary angka">Kontrak sampai {{ $room->activeLease->end_date->translatedFormat('j M Y') }}</div>
                                        @endif
                                    @elseif ($room->status === App\Enums\RoomStatus::Vacant)
                                        <div class="font-label-md text-label-md text-tertiary">Siap disewakan</div>
                                    @endif
                                    @if (! empty($room->facilities))
                                        <div class="text-secondary truncate">{{ implode(' • ', (array) $room->facilities) }}</div>
                                    @endif
                                </div>
                                <div class="pt-space-sm border-t border-outline-variant/30 flex items-center justify-between">
                                    <span class="font-label-lg text-label-lg angka">{{ App\Support\Rupiah::format($room->price) }}<span class="font-body-sm text-body-sm text-secondary">/bln</span></span>
                                    @can('update', $room)
                                        <a href="{{ route('owner.rooms.edit', $room) }}" class="p-1.5 rounded-lg text-secondary hover:text-primary hover:bg-surface-container-low transition-colors" aria-label="Ubah kamar {{ $room->number }}">
                                            <span class="material-symbols-outlined text-base" aria-hidden="true">chevron_right</span>
                                        </a>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>
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
