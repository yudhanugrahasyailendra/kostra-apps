@extends('layouts.owner')

@section('title', 'Ubah penghuni')

@section('content')
    <x-panel :title="$resident->name">
        <form method="POST" action="{{ route('owner.residents.update', $resident) }}" class="flex flex-col gap-space-md">
            @csrf
            @method('PUT')
            @include('owner.residents._form', ['resident' => $resident])
            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.residents.show', $resident)" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>
@endsection
