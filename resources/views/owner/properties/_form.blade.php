{{-- $property: Property|null --}}
<x-input name="name" label="Nama properti" :value="$property?->name" required />
<x-input name="address" label="Alamat" :value="$property?->address" />
<div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
    <x-input name="city" label="Kota" :value="$property?->city" />
    <x-input name="floors" type="number" min="1" max="50" label="Jumlah lantai" :value="$property?->floors" />
</div>
<x-input name="notes" label="Catatan" :value="$property?->notes" />

@if ($property)
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-body-md">
        <input type="checkbox" name="is_active" value="1" class="rounded border-outline-variant" @checked(old('is_active', $property->is_active))>
        Properti aktif
    </label>
@endif
