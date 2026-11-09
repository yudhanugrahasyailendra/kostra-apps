{{-- Satu-satunya pengecualian view Blade selain app.blade.php: dompdf butuh HTML, bukan Inertia. --}}
@php
    $rp = fn (?int $n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $tgl = fn (?string $d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '-';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $receipt['number'] }}</title>
    <style>
        @page { margin: 24px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 16px; color: #00413f; margin: 0 0 2px; }
        .muted { color: #475569; }
        .label { font-size: 9px; text-transform: uppercase; color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 4px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .head td { border: 0; vertical-align: top; padding: 0; }
        .total th, .total td { font-size: 13px; border-top: 1px solid #94a3b8; border-bottom: 0; }
        .void { border: 1px solid #b91c1c; color: #b91c1c; padding: 6px; margin-bottom: 8px; font-weight: bold; }
    </style>
</head>
<body>
    @if ($receipt['status'] === 'voided')
        <div class="void">KUITANSI INI DIBATALKAN. {{ $receipt['void_reason'] }}</div>
    @endif
    <table class="head">
        <tr>
            <td>
                <h1>{{ $receipt['property_name'] ?? $receipt['business_name'] }}</h1>
                @if ($receipt['property_name'] && $receipt['business_name'])<div class="muted">{{ $receipt['business_name'] }}</div>@endif
                @if ($receipt['property_address'])<div class="muted">{{ $receipt['property_address'] }}</div>@endif
            </td>
            <td class="right">
                <div class="label">Kuitansi pembayaran</div>
                <div style="font-size:13px;font-weight:bold">{{ $receipt['number'] }}</div>
                <div class="muted">Terbit {{ $tgl($receipt['issued_at']) }}</div>
            </td>
        </tr>
    </table>
    <br>
    <table class="head">
        <tr>
            <td>
                <div class="label">Diterima dari</div>
                <div style="font-size:13px;font-weight:bold">{{ $receipt['resident_name'] }}</div>
                @if ($receipt['room_number'])<div class="muted">Kamar {{ $receipt['room_number'] }}</div>@endif
            </td>
            <td>
                <div class="label">Tanggal &amp; metode</div>
                <div>{{ $tgl($receipt['paid_on']) }} &middot; {{ $receipt['method_label'] }}</div>
                @if ($receipt['account_label'])<div class="muted">Ke {{ $receipt['account_label'] }}@if ($receipt['account_name']) a.n. {{ $receipt['account_name'] }}@endif</div>@endif
                @if ($receipt['reference'])<div class="muted">Ref. {{ $receipt['reference'] }}</div>@endif
            </td>
        </tr>
    </table>
    <br>
    <table>
        <thead>
            <tr class="label"><th>Untuk pembayaran tagihan</th><th>Periode</th><th class="right">Dibayar</th></tr>
        </thead>
        <tbody>
            @foreach ($receipt['lines'] as $line)
                <tr>
                    <td>{{ $line['invoice_number'] }}@if ($line['invoice_paid']) (lunas)@endif</td>
                    <td>{{ $line['period'] ?? 'Manual' }}</td>
                    <td class="right">{{ $rp($line['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total"><th colspan="2">Total diterima</th><td class="right"><strong>{{ $rp($receipt['amount']) }}</strong></td></tr>
        </tfoot>
    </table>
    <p class="muted" style="margin-top:14px;font-size:9px">
        Dicatat oleh {{ $receipt['recorded_by_name'] ?? 'pengelola' }}. Kuitansi ini mencatat pembayaran yang diterima langsung oleh pemilik kos; Kostra tidak menerima atau menahan dana.
    </p>
</body>
</html>
