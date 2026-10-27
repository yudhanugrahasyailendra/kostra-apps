@extends('layouts.portal')

@section('title', 'Tagihan')

@section('content')
    <h1 class="font-headline-lg text-headline-lg">Tagihan</h1>

    @forelse ($invoices as $invoice)
        <a href="{{ route('portal.invoices.show', ['token' => $portalToken, 'invoice' => $invoice->id]) }}" class="panel p-space-md flex items-center justify-between gap-space-md">
            <div class="min-w-0">
                <div class="font-label-lg angka truncate">{{ $invoice->number }}</div>
                <div class="font-body-sm text-secondary angka">
                    {{ $invoice->period ? \Carbon\CarbonImmutable::parse($invoice->period.'-01')->locale('id')->translatedFormat('F Y') : $invoice->issue_date->translatedFormat('j M Y') }}
                    · Kamar {{ $invoice->lease?->room?->number }}
                </div>
                <x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge>
            </div>
            <div class="text-right">
                <div class="font-label-lg angka">{{ App\Support\Rupiah::format($invoice->total) }}</div>
                @if ($invoice->remaining() > 0)
                    <div class="font-body-sm text-secondary angka">sisa {{ App\Support\Rupiah::format($invoice->remaining()) }}</div>
                @endif
            </div>
        </a>
    @empty
        <div class="panel p-space-md text-on-surface-variant">Belum ada tagihan.</div>
    @endforelse

    {{ $invoices->links() }}
@endsection
