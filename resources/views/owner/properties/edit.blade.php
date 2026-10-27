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
@endsection
