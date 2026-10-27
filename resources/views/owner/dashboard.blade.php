@extends('layouts.owner')

@section('title', 'Ringkasan')

@section('content')
    <x-panel title="Selamat datang, {{ auth()->user()->name }}">
        <p class="text-on-surface-variant">Kos Anda masih kosong. Properti, kamar, dan penghuni akan bisa ditambahkan di tahap berikutnya.</p>
    </x-panel>
@endsection
