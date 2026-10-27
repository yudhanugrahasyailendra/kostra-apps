{{-- $resident: Resident|null --}}
<x-input name="name" label="Nama lengkap" :value="$resident?->name" autocomplete="off" required />
<x-input name="phone" type="tel" label="Nomor WhatsApp" :value="$resident?->phone" hint="Contoh: 0812 3456 7890. Disimpan dalam format internasional (+62)." required />
<div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
    <x-input name="email" type="email" label="Email" :value="$resident?->email" />
    <x-input name="occupation" label="Pekerjaan atau kampus" :value="$resident?->occupation" />
</div>

<fieldset class="flex flex-col gap-space-md">
    <legend class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-1">Kontak darurat</legend>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
        <x-input name="emergency_name" label="Nama" :value="$resident?->emergency_name" />
        <x-input name="emergency_relation" label="Hubungan" :value="$resident?->emergency_relation" />
        <x-input name="emergency_phone" type="tel" label="Nomor telepon" :value="$resident?->emergency_phone" />
    </div>
</fieldset>

<x-input name="notes" label="Catatan internal" :value="$resident?->notes" hint="Hanya terlihat oleh pemilik dan staf, tidak tampil di portal penghuni." />

@if ($resident)
    <p class="text-body-sm text-secondary">Status aktif penghuni mengikuti kontrak: aktif saat check-in, tidak aktif setelah check-out tanpa kontrak lain.</p>
@else
    <label class="flex items-start gap-2 text-body-md">
        <input type="checkbox" name="consent" value="1" class="rounded border-outline-variant mt-1" @checked(old('consent'))>
        <span>Penghuni sudah menyetujui datanya dikumpulkan dan disimpan di Kostra untuk keperluan pengelolaan kos.</span>
    </label>
    @error('consent')
        <p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>
    @enderror
@endif
