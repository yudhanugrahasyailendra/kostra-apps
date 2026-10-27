@extends('layouts.owner')

@section('title', 'Tagihan manual')

@section('content')
    <x-panel :title="'Kamar '.$lease->room->number.' · '.$lease->primaryResident->name">
        <p class="text-secondary mb-space-md">Tagihan di luar siklus bulanan, mis. listrik, kebersihan, atau kerusakan. Langsung terbit dan mendapat nomor.</p>

        <form method="POST" action="{{ route('owner.invoices.store') }}" class="flex flex-col gap-space-md">
            @csrf
            <input type="hidden" name="lease_id" value="{{ $lease->id }}">

            <x-input name="due_date" type="date" label="Jatuh tempo" :value="$today->addDays(7)->toDateString()" min="{{ $today->toDateString() }}" required />

            <fieldset class="flex flex-col gap-space-sm">
                <legend class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-1">Item tagihan</legend>
                @error('items')<p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>@enderror

                @for ($i = 0; $i < $rows; $i++)
                    <div class="grid grid-cols-12 gap-space-sm items-start">
                        <div class="col-span-12 sm:col-span-3">
                            <select name="items[{{ $i }}][type]" aria-label="Jenis item {{ $i + 1 }}"
                                    class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 h-12 lg:h-10">
                                @foreach ($itemTypes as $type)
                                    <option value="{{ $type->value }}" @selected(old("items.$i.type", 'other') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <input type="text" name="items[{{ $i }}][description]" value="{{ old("items.$i.description") }}" placeholder="Deskripsi" aria-label="Deskripsi item {{ $i + 1 }}"
                                   class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 h-12 lg:h-10">
                            @error("items.$i.description")<p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div class="col-span-4 sm:col-span-1">
                            <input type="text" inputmode="decimal" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", '1') }}" aria-label="Kuantitas item {{ $i + 1 }}"
                                   class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 h-12 lg:h-10 angka">
                            @error("items.$i.quantity")<p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div class="col-span-8 sm:col-span-4">
                            <input type="text" inputmode="numeric" name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price") }}" placeholder="Harga satuan (Rp)" aria-label="Harga satuan item {{ $i + 1 }}"
                                   class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 h-12 lg:h-10 angka">
                            @error("items.$i.unit_price")<p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endfor
                <p class="font-body-sm text-body-sm text-secondary">Baris yang dikosongkan diabaikan. Jumlah = kuantitas x harga satuan.</p>
            </fieldset>

            <x-input name="notes" label="Catatan" />

            <div class="flex gap-2">
                <x-button type="submit">Terbitkan tagihan</x-button>
                <x-button :href="route('owner.leases.show', $lease)" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>
@endsection
