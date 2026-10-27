@extends('layouts.owner')

@section('title', 'Buat tiket komplain')

@section('content')
    <x-panel title="Buat tiket atas nama penghuni">
        @if ($residents->isEmpty())
            <p class="text-on-surface-variant">Belum ada penghuni dengan kontrak aktif. Tiket dibuat untuk penghuni yang sedang menyewa kamar.</p>
        @else
            <form method="POST" action="{{ route('owner.complaints.store') }}" enctype="multipart/form-data" class="flex flex-col gap-space-md max-w-2xl">
                @csrf

                <x-select name="resident_id" label="Penghuni" placeholder="Pilih penghuni" required>
                    @foreach ($residents as $resident)
                        <option value="{{ $resident->id }}" @selected((int) old('resident_id', request('resident_id')) === $resident->id)>{{ $resident->name }}</option>
                    @endforeach
                </x-select>
                <p class="font-body-sm text-secondary -mt-space-sm">Kamar diambil dari kontrak aktif penghuni.</p>

                <x-select name="category" label="Kategori" required>
                    @foreach ($categories as $case)
                        <option value="{{ $case->value }}" @selected(old('category') === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </x-select>

                <x-input name="title" label="Judul" maxlength="150" required />

                <div class="flex flex-col gap-1">
                    <label for="description" class="font-label-md text-label-md text-on-surface">Deskripsi</label>
                    <textarea id="description" name="description" rows="5" maxlength="5000" required
                              class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 py-2 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <x-input name="photo" type="file" accept=".jpg,.jpeg,.png" label="Foto (opsional, JPG atau PNG, maksimal 5 MB)" />

                <div class="flex gap-2">
                    <x-button type="submit">Buat tiket</x-button>
                    <x-button :href="route('owner.complaints.index')" variant="secondary">Batal</x-button>
                </div>
            </form>
        @endif
    </x-panel>
@endsection
