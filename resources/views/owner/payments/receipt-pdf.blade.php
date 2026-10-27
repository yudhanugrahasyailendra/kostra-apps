<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $payment->receipt_number }}</title>
    {{-- Dompdf tidak mendukung flex, grid, maupun Tailwind: tata letak memakai tabel dan CSS inline dengan token DESIGN.md. --}}
    <style>
        @page { margin: 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0b1c30; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #545f73; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #545f73; }
        .right { text-align: right; }
        .head td { padding-bottom: 10px; border-bottom: 1px solid #bdc9c2; vertical-align: top; }
        .title { font-size: 16px; font-weight: bold; }
        .number { font-size: 13px; font-weight: bold; }
        .info td { padding: 4px 0; vertical-align: top; }
        .info td.k { width: 30%; color: #545f73; }
        .amount { font-size: 14px; font-weight: bold; color: #005f49; }
        .items th { background: #eff4ff; padding: 6px; text-align: left; }
        .items th.right { text-align: right; }
        .items td { padding: 6px; border-top: 1px solid #bdc9c2; vertical-align: top; }
        .void { margin-top: 10px; padding: 6px 8px; background: #ffdad6; color: #93000a; }
        .stamp { margin-top: 12px; text-align: center; font-size: 18px; font-weight: bold; color: #ba1a1a; border: 3px solid #ba1a1a; padding: 4px; }
        .sign td { vertical-align: bottom; }
        .line { border-top: 1px solid #0b1c30; padding: 4px 24px 0; text-align: center; }
    </style>
</head>
<body>
    @php($tz = $tenant->timezone)

    <table class="head">
        <tr>
            <td>
                <div class="title">{{ $tenant->name }}</div>
                <div class="label">Kuitansi pembayaran</div>
            </td>
            <td class="right">
                <div class="label">Nomor</div>
                <div class="number">{{ $payment->receipt_number }}</div>
                <div class="muted">{{ $payment->paid_on->translatedFormat('j F Y') }}</div>
            </td>
        </tr>
    </table>

    @if ($payment->isVoided())
        <div class="stamp">DIBATALKAN</div>
        <div class="void">
            Kuitansi ini dibatalkan pada {{ $payment->voided_at?->timezone($tz)->translatedFormat('j F Y') }}. Alasan: {{ $payment->void_reason }}
        </div>
    @endif

    <table class="info" style="margin-top: 12px;">
        <tr><td class="k">Diterima dari</td><td><strong>{{ $payment->resident->name }}</strong></td></tr>
        <tr><td class="k">Jumlah</td><td class="amount">{{ App\Support\Rupiah::format($payment->amount) }}</td></tr>
        <tr>
            <td class="k">Metode</td>
            <td>{{ $payment->method->label() }}@if ($payment->account) · {{ $payment->account->label() }}@endif</td>
        </tr>
        @if ($payment->reference)
            <tr><td class="k">Referensi</td><td>{{ $payment->reference }}</td></tr>
        @endif
    </table>

    <table class="items" style="margin-top: 14px;">
        <thead>
            <tr><th>Untuk pembayaran tagihan</th><th class="right">Jumlah</th></tr>
        </thead>
        <tbody>
            @foreach ($payment->allocations as $allocation)
                <tr>
                    <td>
                        {{ $allocation->invoice->number }}
                        @foreach ($allocation->invoice->items->take(2) as $item)
                            <div class="muted">{{ $item->description }}</div>
                        @endforeach
                    </td>
                    <td class="right">{{ App\Support\Rupiah::format($allocation->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign" style="margin-top: 28px;">
        <tr>
            <td class="muted" style="width: 55%;">Pembayaran dicatat setelah diterima di luar sistem. Kuitansi ini diterbitkan oleh {{ $tenant->name }} melalui Kostra.</td>
            <td style="height: 70px;"><div class="line">{{ $payment->recorder?->name ?? 'Pengelola' }}</div></td>
        </tr>
    </table>
</body>
</html>
