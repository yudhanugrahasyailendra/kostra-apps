@extends('layouts.owner')

@section('title', 'Ubah properti')

@section('content')
    <x-panel :title="$property->name">
        <form method="POST" action="{{ route('owner.properties.update', $property) }}" class="flex flex-col gap-space-md">
            @csrf
            @method('PUT')
            @include('owner.properties._form', ['property' => $property])
            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.properties.index')" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>

    <x-panel title="Foto properti">
        @if ($property->photo)
            <img src="{{ $property->photo->temporaryUrl() }}" alt="Foto {{ $property->name }}" class="max-h-64 rounded-lg mb-space-md">
            <form method="POST" action="{{ route('owner.attachments.destroy', $property->photo) }}" class="mb-space-md" onsubmit="return confirm('Hapus foto ini?')">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger">Hapus foto</x-button>
            </form>
        @endif
        <form method="POST" action="{{ route('owner.properties.photo.store', $property) }}" enctype="multipart/form-data" class="flex flex-col gap-space-md">
            @csrf
            <x-input name="file" type="file" accept=".jpg,.jpeg,.png" label="{{ $property->photo ? 'Ganti foto' : 'Unggah foto' }} (JPG atau PNG, maksimal 5 MB)" required />
            <div><x-button type="submit" variant="secondary">Unggah</x-button></div>
        </form>
    </x-panel>
@endsection
