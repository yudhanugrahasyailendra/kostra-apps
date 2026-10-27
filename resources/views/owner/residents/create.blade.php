@extends('layouts.owner')

@section('title', 'Tambah penghuni')

@section('content')
    <x-panel title="Data penghuni">
        <form method="POST" action="{{ route('owner.residents.store') }}" class="flex flex-col gap-space-md">
            @csrf
            @include('owner.residents._form', ['resident' => null])
            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.residents.index')" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>
@endsection
