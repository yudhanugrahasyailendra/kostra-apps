{{-- $account: PaymentAccount|null; $types; $properties --}}
<x-select name="type" label="Jenis">
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $account?->type->value ?? 'bank') === $type->value)>{{ $type->label() }}</option>
    @endforeach
</x-select>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
    <x-input name="bank_name" label="Nama bank atau dompet digital" :value="$account?->bank_name" hint="Mis. BCA, Mandiri, GoPay. Kosongkan untuk QRIS." />
    <x-input name="account_number" label="Nomor rekening atau akun" :value="$account?->account_number" hint="Kosongkan untuk QRIS." />
</div>
<x-input name="account_name" label="Atas nama" :value="$account?->account_name" />
<x-input name="instructions" label="Catatan untuk penghuni" :value="$account?->instructions" hint="Tampil bersama rekening di tagihan dan portal." />
<x-select name="property_id" label="Berlaku untuk" placeholder="Semua properti">
    @foreach ($properties as $property)
        <option value="{{ $property->id }}" @selected((int) old('property_id', $account?->property_id) === $property->id)>{{ $property->name }}</option>
    @endforeach
</x-select>

<input type="hidden" name="is_default" value="0">
<label class="flex items-center gap-2 text-body-md">
    <input type="checkbox" name="is_default" value="1" class="rounded border-outline-variant" @checked(old('is_default', $account?->is_default))>
    Jadikan rekening utama
</label>

@if ($account)
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-body-md">
        <input type="checkbox" name="is_active" value="1" class="rounded border-outline-variant" @checked(old('is_active', $account->is_active))>
        Rekening aktif
    </label>
@endif
