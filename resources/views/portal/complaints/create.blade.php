@extends('layouts.portal')

@section('title', 'Ajukan komplain')

@section('content')
    <a href="{{ route('portal.complaints.index', ['token' => $portalToken]) }}" class="text-primary">&larr; Komplain</a>

    <section class="panel p-space-md">
        <h1 class="font-headline-md text-headline-md mb-space-md">Ajukan komplain</h1>
        <form method="POST" enctype="multipart/form-data" action="{{ route('portal.complaints.store', ['token' => $portalToken]) }}" class="flex flex-col gap-space-md">
            @csrf
            <x-select name="category" label="Kategori" required>
                @foreach ($categories as $case)
                    <option value="{{ $case->value }}" @selected(old('category') === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </x-select>
            <x-input name="title" label="Judul singkat" maxlength="150" required />

            <div class="flex flex-col gap-1">
                <label for="description" class="font-label-md text-label-md text-on-surface">Ceritakan masalahnya</label>
                <textarea id="description" name="description" rows="5" maxlength="5000" required
                          class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 py-2 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('description') }}</textarea>
            </div>

            <x-input name="photo" type="file" accept=".jpg,.jpeg,.png" label="Foto (opsional, JPG atau PNG, maksimal 5 MB)" />
            <x-button type="submit">Kirim keluhan</x-button>
        </form>
    </section>
@endsection
