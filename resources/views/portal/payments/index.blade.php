@extends('layouts.portal')

@section('title', 'Riwayat pembayaran')

@section('content')
    <h1 class="font-headline-lg text-headline-lg">Riwayat pembayaran</h1>

    @forelse ($payments as $payment)
        <div class="panel p-space-md flex items-center justify-between gap-space-md">
            <div class="min-w-0">
                <div class="font-label-lg angka">{{ App\Support\Rupiah::format($payment->amount) }}</div>
                <div class="font-body-sm text-secondary angka">{{ $payment->paid_on->translatedFormat('j F Y') }} · {{ $payment->method->label() }}</div>
                <div class="font-body-sm text-secondary angka truncate">{{ $payment->allocations->map(fn ($a) => $a->invoice->number)->implode(', ') }}</div>
            </div>
            <a class="text-primary whitespace-nowrap" href="{{ route('portal.payments.receipt', ['token' => $portalToken, 'payment' => $payment->id]) }}">Kuitansi</a>
        </div>
    @empty
        <div class="panel p-space-md text-on-surface-variant">Belum ada pembayaran yang tercatat.</div>
    @endforelse

    {{ $payments->links() }}
@endsection
