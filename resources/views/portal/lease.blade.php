@extends('layouts.portal')

@section('title', 'Kontrak')

@section('content')
    <h1 class="font-headline-lg text-headline-lg">Kontrak</h1>

    @forelse ($leases as $lease)
        <section class="panel p-space-md">
            <div class="flex items-start justify-between gap-space-sm mb-space-sm">
                <div class="font-label-lg angka">{{ $lease->property->name }} · Kamar {{ $lease->room->number }}</div>
                <x-status-badge :tone="$lease->status->tone()">{{ $lease->status->label() }}</x-status-badge>
            </div>
            <dl class="grid grid-cols-2 gap-y-space-sm">
                <dt class="text-secondary">Mulai</dt><dd class="angka">{{ $lease->start_date->translatedFormat('j F Y') }}</dd>
                <dt class="text-secondary">Berakhir</dt><dd class="angka">{{ $lease->end_date?->translatedFormat('j F Y') ?? 'Tanpa batas' }}</dd>
                <dt class="text-secondary">Sewa</dt><dd class="angka">{{ App\Support\Rupiah::format($lease->rent_amount) }}</dd>
                @if ($lease->due_day)
                    <dt class="text-secondary">Jatuh tempo</dt><dd>Tanggal {{ $lease->due_day }} tiap bulan</dd>
                @endif
            </dl>
            @if ($lease->status === App\Enums\LeaseStatus::Active && $lease->end_date && $lease->end_date->between($today, $today->addDays(App\Models\Lease::EXPIRING_SOON_DAYS)))
                <p class="mt-space-sm rounded-xl bg-tertiary-fixed text-on-tertiary-fixed px-space-md py-space-sm font-label-md" role="status">Kontrak Anda akan segera berakhir. Hubungi pengelola bila ingin memperpanjang.</p>
            @endif
        </section>
    @empty
        <div class="panel p-space-md text-on-surface-variant">Belum ada kontrak.</div>
    @endforelse
@endsection
