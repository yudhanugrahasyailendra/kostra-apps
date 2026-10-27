@extends('layouts.portal')

@section('title', 'Komplain')

@section('content')
    <div class="flex items-center justify-between gap-space-sm">
        <h1 class="font-headline-lg text-headline-lg">Komplain</h1>
        <x-button :href="route('portal.complaints.create', ['token' => $portalToken])">Ajukan</x-button>
    </div>

    @forelse ($complaints as $complaint)
        <a href="{{ route('portal.complaints.show', ['token' => $portalToken, 'complaint' => $complaint->id]) }}" class="panel p-space-md flex items-center justify-between gap-space-md">
            <div class="min-w-0">
                <div class="font-label-lg truncate">{{ $complaint->title }}</div>
                <div class="font-body-sm text-secondary">{{ $complaint->category->label() }} · Kamar {{ $complaint->room?->number }}</div>
            </div>
            <x-status-badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-status-badge>
        </a>
    @empty
        <div class="panel p-space-md text-on-surface-variant">Belum ada keluhan. Ada yang rusak atau mengganggu? Ajukan di sini.</div>
    @endforelse

    {{ $complaints->links() }}
@endsection
