@extends('layouts.owner')

@section('title', 'Check-in penghuni')

@section('content')
    <x-panel title="Data kontrak">
        @if ($rooms->isEmpty())
            <p class="text-on-surface-variant">Tidak ada kamar kosong. Kamar yang sedang terisi atau dalam perawatan tidak bisa dipakai check-in.</p>
        @elseif ($residents->isEmpty())
            <p class="text-on-surface-variant">Belum ada penghuni. <a class="text-primary hover:underline" href="{{ route('owner.residents.create') }}">Tambah penghuni</a> lebih dulu.</p>
        @else
            <form method="POST" action="{{ route('owner.leases.store') }}" class="flex flex-col gap-space-md">
                @csrf

                <x-select name="room_id" label="Kamar (hanya kamar kosong)" placeholder="Pilih kamar" required>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected((int) old('room_id', $selectedRoom) === $room->id)>
                            {{ $room->property->name }} · Kamar {{ $room->number }} · {{ App\Support\Rupiah::format($room->price) }}
                        </option>
                    @endforeach
                </x-select>

                <x-select name="primary_resident_id" label="Penanggung jawab tagihan" placeholder="Pilih penghuni" required>
                    @foreach ($residents as $resident)
                        <option value="{{ $resident->id }}" @selected((int) old('primary_resident_id', $selectedResident) === $resident->id)>
                            {{ $resident->name }} · {{ $resident->phone }}@unless ($resident->is_active) (tidak aktif)@endunless
                        </option>
                    @endforeach
                </x-select>

                <div class="flex flex-col gap-1">
                    <label for="other_resident_ids" class="font-label-md text-label-md">Penghuni lain di kamar yang sama (opsional)</label>
                    <select id="other_resident_ids" name="other_resident_ids[]" multiple size="4"
                            class="w-full field h-auto py-2 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        @foreach ($residents as $resident)
                            <option value="{{ $resident->id }}" @selected(in_array($resident->id, array_map('intval', (array) old('other_resident_ids', []))))>{{ $resident->name }}</option>
                        @endforeach
                    </select>
                    <p class="font-body-sm text-body-sm text-secondary">Tahan Ctrl (atau Cmd) untuk memilih lebih dari satu. Maksimal 5 orang.</p>
                    @error('other_resident_ids')<p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>@enderror
                    @error('other_resident_ids.*')<p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                    <x-input name="start_date" type="date" label="Tanggal mulai (check-in)" :value="$today" max="{{ $today }}" required />
                    <x-input name="end_date" type="date" label="Tanggal akhir kontrak" hint="Kosongkan bila tanpa tanggal akhir." />
                    <x-input name="rent_amount" inputmode="numeric" label="Harga sewa per bulan (Rp)" hint="Kosongkan untuk memakai harga kamar. Harga ini disimpan di kontrak." />
                    <x-input name="due_day" type="number" min="1" max="28" label="Tanggal jatuh tempo tiap bulan" hint="Kosongkan untuk mengikuti pengaturan penagihan." />
                </div>

                <fieldset class="flex flex-col gap-space-md">
                    <legend class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-1">Deposit (hanya dicatat)</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                        <x-input name="deposit_amount" inputmode="numeric" label="Nominal deposit (Rp)" hint="Isi 0 bila tanpa deposit." />
                        <x-input name="deposit_received_on" type="date" label="Tanggal deposit diterima" max="{{ $today }}" hint="Kosongkan bila belum diterima." />
                    </div>
                </fieldset>

                <x-input name="checkin_notes" label="Catatan kondisi kamar saat masuk" />
                <x-input name="notes" label="Catatan kontrak" />

                <div class="flex gap-2">
                    <x-button type="submit">Check-in</x-button>
                    <x-button :href="route('owner.leases.index')" variant="secondary">Batal</x-button>
                </div>
            </form>
        @endif
    </x-panel>
@endsection
