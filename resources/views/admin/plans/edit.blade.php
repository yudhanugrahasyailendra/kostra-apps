@extends('layouts.owner')

@section('title', 'Ubah paket '.$plan->name)

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('content')
    <x-panel :title="'Ubah paket '.$plan->name">
        <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="flex flex-col gap-space-md max-w-2xl">
            @csrf
            @method('PUT')

            <x-input name="name" label="Nama" :value="$plan->name" maxlength="60" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <x-input name="price_monthly" type="number" min="0" label="Harga per bulan (rupiah)" :value="$plan->price_monthly" required />
                <x-input name="price_yearly" type="number" min="1" label="Harga per tahun (kosong = tidak ada)" :value="$plan->price_yearly" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
                <x-input name="max_properties" type="number" min="1" label="Batas properti (kosong = tanpa batas)" :value="$plan->max_properties" />
                <x-input name="max_rooms" type="number" min="1" label="Batas kamar (kosong = tanpa batas)" :value="$plan->max_rooms" />
                <x-input name="max_users" type="number" min="1" label="Batas pengguna (kosong = tanpa batas)" :value="$plan->max_users" />
            </div>

            <x-input name="monthly_message_quota" type="number" min="0" label="Kuota pesan WhatsApp per bulan" :value="$plan->monthly_message_quota" required />

            <fieldset class="flex flex-col gap-space-sm">
                <legend class="font-label-md text-label-md mb-1">Fitur</legend>
                @foreach ($features as $key => $label)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, old('features', $plan->features ?? []), true)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                        {{ $label }}
                    </label>
                @endforeach
            </fieldset>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md items-end">
                <x-input name="sort_order" type="number" min="0" max="255" label="Urutan tampil" :value="$plan->sort_order" required />
                <label class="flex items-center gap-2 font-label-md h-12 lg:h-10">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                    Paket aktif (bisa dipilih pemilik)
                </label>
            </div>

            <p class="font-body-sm text-secondary">Harga baru hanya berlaku untuk tagihan berikutnya. Batas dan kuota berlaku seketika bagi tenant di paket ini.</p>

            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('admin.plans.index')" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>
@endsection
