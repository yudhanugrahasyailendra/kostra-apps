@extends('layouts.owner')

@section('title', 'Panel Super Admin')

@section('nav')
    <a href="{{ route('admin.dashboard') }}" aria-current="page" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-primary text-on-primary font-label-lg">
        <span class="material-symbols-outlined text-lg" aria-hidden="true">dashboard</span>
        <span>Ringkasan</span>
    </a>
@endsection

@section('content')
    <x-panel title="Panel Super Admin">
        <p class="text-on-surface-variant">Daftar tenant dan langganan akan tersedia di Fase 11.</p>
    </x-panel>
@endsection
