@extends('layouts.portal')

@section('title', 'Beranda')

@section('content')
    <section class="panel p-space-md">
        <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Yang perlu dibayar</div>
        <div class="font-currency-stat text-currency-stat angka" data-stat="outstanding">{{ App\Support\Rupiah::format($outstanding) }}</div>
        <p class="font-body-sm text-secondary">
            @if ($invoices->isEmpty())
                Tidak ada tagihan yang menunggu pembayaran.
            @else
                {{ $invoices->count() }} tagihan terbuka
            @endif
        </p>
    </section>

    @foreach ($invoices as $invoice)
        @php($late = $invoice->due_date->lt($today))
        <a href="{{ route('portal.invoices.show', ['token' => $portalToken, 'invoice' => $invoice->id]) }}" class="panel p-space-md flex items-center justify-between gap-space-md">
            <div class="min-w-0">
                <div class="font-label-lg angka truncate">{{ $invoice->number }}</div>
                <div class="font-body-sm text-secondary angka">Jatuh tempo {{ $invoice->due_date->translatedFormat('j M Y') }}</div>
                <x-status-badge :tone="$late ? 'danger' : $invoice->status->tone()">{{ $late ? 'Terlambat' : $invoice->status->label() }}</x-status-badge>
            </div>
            <div class="text-right">
                <div class="font-label-lg angka">{{ App\Support\Rupiah::format($invoice->remaining()) }}</div>
                <div class="font-body-sm text-primary">Bayar</div>
            </div>
        </a>
    @endforeach

    @if ($pendingProofs > 0)
        <div class="rounded-xl bg-tertiary-fixed text-on-tertiary-fixed px-space-md py-space-sm font-label-md" role="status">
            {{ $pendingProofs }} bukti transfer menunggu diperiksa pengelola.
        </div>
    @endif

    <section class="panel p-space-md">
        <h2 class="font-headline-md text-headline-md mb-space-sm">Kontrak</h2>
        @if ($lease)
            <dl class="grid grid-cols-2 gap-y-space-sm text-body-md">
                <dt class="text-secondary">Kamar</dt><dd class="angka">{{ $lease->property->name }} · {{ $lease->room->number }}</dd>
                <dt class="text-secondary">Berakhir</dt><dd class="angka">{{ $lease->end_date?->translatedFormat('j F Y') ?? 'Tanpa batas' }}</dd>
            </dl>
        @else
            <p class="text-on-surface-variant">Belum ada kontrak aktif.</p>
        @endif
        <a class="mt-space-sm inline-block text-primary" href="{{ route('portal.lease', ['token' => $portalToken]) }}">Lihat kontrak</a>
    </section>

    <section class="panel p-space-md flex items-center justify-between gap-space-md">
        <div>
            <h2 class="font-headline-md text-headline-md">Komplain</h2>
            <p class="font-body-sm text-secondary">{{ $openComplaints }} keluhan sedang ditangani</p>
        </div>
        <x-button :href="route('portal.complaints.create', ['token' => $portalToken])" variant="secondary">Ajukan</x-button>
    </section>
@endsection
