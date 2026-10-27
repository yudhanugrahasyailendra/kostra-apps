@extends('layouts.portal')

@section('title', 'Kuitansi '.$payment->receipt_number)

@section('content')
    <a href="{{ route('portal.payments.index', ['token' => $portalToken]) }}" class="text-primary">&larr; Riwayat pembayaran</a>

    <article class="panel p-space-md flex flex-col gap-space-md">
        <header class="border-b border-outline-variant pb-space-sm">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Kuitansi pembayaran</div>
            <div class="font-headline-md text-headline-md angka">{{ $payment->receipt_number }}</div>
            <div class="font-body-sm text-secondary angka">{{ $payment->paid_on->translatedFormat('j F Y') }}</div>
        </header>

        <dl class="grid grid-cols-3 gap-y-space-sm">
            <dt class="text-secondary">Diterima dari</dt><dd class="col-span-2 font-label-lg">{{ $payment->resident->name }}</dd>
            <dt class="text-secondary">Jumlah</dt><dd class="col-span-2 font-headline-md text-headline-md angka">{{ App\Support\Rupiah::format($payment->amount) }}</dd>
            <dt class="text-secondary">Metode</dt><dd class="col-span-2">{{ $payment->method->label() }}</dd>
        </dl>

        <table class="table">
            <thead>
                <tr><th>Untuk tagihan</th><th class="text-right">Jumlah</th></tr>
            </thead>
            <tbody>
                @foreach ($payment->allocations as $allocation)
                    <tr>
                        <td class="angka">{{ $allocation->invoice->number }}</td>
                        <td class="angka text-right">{{ App\Support\Rupiah::format($allocation->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <x-button :href="route('portal.payments.pdf', ['token' => $portalToken, 'payment' => $payment->id])" variant="secondary">Unduh PDF</x-button>
        <p class="font-body-sm text-secondary">Pembayaran dicatat setelah diterima di luar sistem. Kuitansi diterbitkan oleh {{ $tenant->name }} melalui Kostra.</p>
    </article>
@endsection
