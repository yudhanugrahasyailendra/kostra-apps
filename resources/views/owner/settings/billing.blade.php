@extends('layouts.owner')

@section('title', 'Pengaturan penagihan')

@section('content')
    <x-panel title="Aturan tagihan bulanan">
        <form method="POST" action="{{ route('owner.settings.billing.update') }}" class="flex flex-col gap-space-md">
            @csrf
            @method('PUT')

            <x-input name="invoice_prefix" label="Awalan nomor tagihan" :value="$settings->invoice_prefix" hint="Contoh nomor: {{ $settings->invoice_prefix }}-{{ now()->format('Y-m') }}-0001" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <x-input name="issue_day" type="number" min="1" max="28" label="Tanggal terbit tiap bulan" :value="$settings->issue_day"
                         hint="Tagihan terbit otomatis mulai tanggal ini. Kontrak yang baru check-in setelahnya ikut tertagih pada hari berikutnya." required />
                <x-input name="due_day" type="number" min="1" max="28" label="Tanggal jatuh tempo bawaan" :value="$settings->due_day"
                         hint="Dapat diganti per kontrak." required />
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-1">Pro rata (sewa sebagian bulan)</legend>
                <input type="hidden" name="prorate_first_month" value="0">
                <label class="flex items-start gap-2 text-body-md">
                    <input type="checkbox" name="prorate_first_month" value="1" class="rounded border-outline-variant mt-1" @checked(old('prorate_first_month', $settings->prorate_first_month))>
                    <span>Bulan pertama: tagih hanya sejak tanggal mulai kontrak.</span>
                </label>
                <input type="hidden" name="prorate_last_month" value="0">
                <label class="flex items-start gap-2 text-body-md">
                    <input type="checkbox" name="prorate_last_month" value="1" class="rounded border-outline-variant mt-1" @checked(old('prorate_last_month', $settings->prorate_last_month))>
                    <span>Bulan terakhir: tagih hanya sampai tanggal akhir kontrak.</span>
                </label>
                <p class="font-body-sm text-body-sm text-secondary">Jumlah = sewa x hari ditagih / hari dalam bulan, dibulatkan ke rupiah. Bila mati, bulan itu ditagih penuh.</p>
            </fieldset>

            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.settings')" variant="secondary">Kembali</x-button>
            </div>
        </form>
    </x-panel>
@endsection
