{{-- Dompdf butuh HTML, bukan Inertia. Isi tabel berasal dari ReportTable yang sama dengan layar. --}}
@php
    $fmt = function (string $type, $value) {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => ((int) $value < 0 ? '-' : '').'Rp '.number_format(abs((int) $value), 0, ',', '.'),
            // Baris total memakai label teks ("Total") di kolom tanggal; hanya Y-m-d yang diformat.
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : $value,
            'percent' => number_format((float) $value, 1, ',', '.').'%',
            'number' => is_numeric($value) ? number_format((float) $value, fmod((float) $value, 1) === 0.0 ? 0 : 1, ',', '.') : $value,
            default => $value,
        };
    };
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 16px; color: #00413f; margin: 0 0 4px; }
        .muted { color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1px 0; border: 0; }
        .meta td:first-child { width: 90px; color: #475569; }
        .data th { background: #f1f5f9; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; }
        .data th, .data td { padding: 4px; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .total td { font-weight: bold; border-top: 1px solid #94a3b8; }
        .summary { margin: 8px 0; }
        .summary td { padding: 2px 12px 2px 0; border: 0; width: auto; }
        .empty { padding: 16px; text-align: center; color: #475569; }
    </style>
</head>
<body>
    <h1>{{ $table->title }}</h1>
    <table class="meta">
        <tr><td>Kos</td><td>{{ $meta['business'] }}</td></tr>
        <tr><td>Properti</td><td>{{ $meta['property'] }}</td></tr>
        <tr><td>Periode</td><td>{{ $meta['period'] }}</td></tr>
        <tr><td>Tanggal cetak</td><td>{{ $meta['printed_at'] }}</td></tr>
    </table>

    @if ($table->summary !== [])
        <table class="summary">
            @foreach ($table->summary as $item)
                <tr><td class="muted">{{ $item['label'] }}</td><td><strong>{{ $fmt($item['type'], $item['value']) }}</strong></td></tr>
            @endforeach
        </table>
    @endif

    <br>
    <table class="data">
        <thead>
            <tr>
                @foreach ($table->columns as $column)
                    <th class="{{ in_array($column['type'], ['money', 'number', 'percent'], true) ? 'right' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($table->rows as $row)
                <tr>
                    @foreach ($table->columns as $column)
                        <td class="{{ in_array($column['type'], ['money', 'number', 'percent'], true) ? 'right' : '' }}">{{ $fmt($column['type'], $row[$column['key']] ?? null) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($table->columns) }}">Tidak ada data untuk filter ini.</td></tr>
            @endforelse
            @if ($table->totals !== [])
                <tr class="total">
                    @foreach ($table->columns as $column)
                        <td class="{{ in_array($column['type'], ['money', 'number', 'percent'], true) ? 'right' : '' }}">{{ $fmt($column['type'], $table->totals[$column['key']] ?? null) }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
