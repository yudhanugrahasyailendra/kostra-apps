<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Kuitansi {{ $payment->receipt_number }}</title>
    @include('layouts.fonts')
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .receipt { box-shadow: none !important; border: 1px solid #94a3b8 !important; }
        }
    </style>
</head>
<body class="bg-background text-on-surface">
    @php($tz = $tenant->timezone)
    <main class="max-w-2xl mx-auto p-margin">
        <div class="no-print flex justify-between items-center mb-space-md">
            <a href="{{ route('owner.payments.show', $payment) }}" class="text-primary hover:underline">&larr; Kembali</a>
            <div class="flex gap-2">
                <a href="{{ route('owner.payments.receipt.pdf', $payment) }}" class="btn btn-secondary">Unduh PDF</a>
                <button type="button" onclick="window.print()" class="btn btn-primary">Cetak atau simpan PDF</button>
            </div>
        </div>

        <article class="receipt panel p-space-lg relative overflow-hidden">
            @if ($payment->isVoided())
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
                    <span class="font-headline-xl text-headline-xl text-error opacity-25 -rotate-12 border-4 border-error px-6 py-2">DIBATALKAN</span>
                </div>
            @endif

            <header class="flex flex-wrap items-start justify-between gap-space-md border-b border-outline-variant pb-space-md">
                <div>
                    <div class="font-headline-lg text-headline-lg">{{ $tenant->name }}</div>
                    <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Kuitansi pembayaran</div>
                </div>
                <div class="text-right">
                    <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Nomor</div>
                    <div class="font-headline-md text-headline-md angka">{{ $payment->receipt_number }}</div>
                    <div class="font-body-sm text-secondary angka">{{ $payment->paid_on->translatedFormat('j F Y') }}</div>
                </div>
            </header>

            @if ($payment->isVoided())
                <p class="mt-space-md rounded-lg bg-error-container text-on-error-container px-3 py-2 font-label-md" role="status">
                    Kuitansi ini dibatalkan pada {{ $payment->voided_at?->timezone($tz)->translatedFormat('j F Y') }}. Alasan: {{ $payment->void_reason }}
                </p>
            @endif

            <dl class="mt-space-md grid grid-cols-3 gap-y-space-sm text-body-md">
                <dt class="text-secondary">Diterima dari</dt><dd class="col-span-2 font-label-lg">{{ $payment->resident->name }}</dd>
                <dt class="text-secondary">Jumlah</dt><dd class="col-span-2 font-headline-md text-headline-md angka">{{ App\Support\Rupiah::format($payment->amount) }}</dd>
                <dt class="text-secondary">Metode</dt>
                <dd class="col-span-2">{{ $payment->method->label() }}@if ($payment->account) · {{ $payment->account->label() }}@endif</dd>
                @if ($payment->reference)
                    <dt class="text-secondary">Referensi</dt><dd class="col-span-2 angka">{{ $payment->reference }}</dd>
                @endif
            </dl>

            <table class="w-full text-left mt-space-md">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr><th class="p-2">Untuk pembayaran tagihan</th><th class="p-2 text-right">Jumlah</th></tr>
                </thead>
                <tbody>
                    @foreach ($payment->allocations as $allocation)
                        <tr class="border-t border-outline-variant/40">
                            <td class="p-2">
                                <span class="angka">{{ $allocation->invoice->number }}</span>
                                @foreach ($allocation->invoice->items->take(2) as $item)
                                    <div class="font-body-sm text-secondary">{{ $item->description }}</div>
                                @endforeach
                            </td>
                            <td class="p-2 angka text-right">{{ App\Support\Rupiah::format($allocation->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <footer class="mt-space-lg flex justify-between items-end">
                <p class="font-body-sm text-secondary max-w-xs">Pembayaran dicatat setelah diterima di luar sistem. Kuitansi ini diterbitkan oleh {{ $tenant->name }} melalui Kostra.</p>
                <div class="text-center">
                    <div class="h-16"></div>
                    <div class="border-t border-on-surface px-8 font-body-sm">{{ $payment->recorder?->name ?? 'Pengelola' }}</div>
                </div>
            </footer>
        </article>
    </main>
</body>
</html>
