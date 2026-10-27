{{-- $type: RoomType|null; $properties: daftar properti (hanya saat membuat) --}}
<x-input name="name" label="Nama tipe" :value="$type?->name" required />
<x-input name="base_price" inputmode="numeric" label="Harga dasar per bulan (Rp)" :value="$type ? number_format($type->base_price, 0, ',', '.') : null"
         hint="Mengubah harga tipe tidak mengubah harga kamar yang sudah ada." required />
<x-input name="size_m2" inputmode="decimal" label="Ukuran (m²)" :value="$type?->size_m2" />
<x-input name="facilities" label="Fasilitas" :value="$type ? implode(', ', $type->facilities ?? []) : null" hint="Pisahkan dengan koma, mis. AC, Kamar mandi dalam." />
<x-input name="description" label="Deskripsi" :value="$type?->description" />

@if (! $type)
    <x-select name="property_id" label="Berlaku untuk" :selected="old('property_id')">
        <option value="">Semua properti</option>
        @foreach ($properties as $property)
            <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>{{ $property->name }}</option>
        @endforeach
    </x-select>
@else
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-body-md">
        <input type="checkbox" name="is_active" value="1" class="rounded border-outline-variant" @checked(old('is_active', $type->is_active))>
        Tipe aktif
    </label>
@endif
