<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title }}</title>
    {{-- Dompdf tidak mendukung flex/grid/Tailwind: tabel dan CSS inline dengan token DESIGN.md. --}}
    <style>
        @page { margin: 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #0b1c30; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1px 8px 1px 0; }
        .meta td.k { color: #545f73; width: 90px; }
        .data { margin-top: 12px; }
        .data th { background: #eff4ff; color: #545f73; text-align: left; padding: 5px; font-size: 8px; text-transform: uppercase; }
        .data td { border-top: 1px solid #bdc9c2; padding: 5px; }
        .right { text-align: right; }
        .total td { border-top: 2px solid #bdc9c2; font-weight: bold; }
        .summary { margin-top: 10px; }
        .summary td { padding: 2px 8px 2px 0; }
    </style>
</head>
<body>
    <h1>{{ $table->title }}</h1>

    <table class="meta">
        @foreach ($table->meta as $label => $value)
            <tr><td class="k">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <table class="data">
        <thead>
            <tr>
                @foreach ($table->columns as $column)
                    <th class="{{ $table->isRightAligned($column['type']) ? 'right' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($table->rows as $row)
                <tr>
                    @foreach ($table->columns as $column)
                        <td class="{{ $table->isRightAligned($column['type']) ? 'right' : '' }}">{{ App\Services\Reports\ReportTable::display($column, $row[$column['key']] ?? null) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($table->columns) }}">Tidak ada data untuk filter ini.</td></tr>
            @endforelse
            @if ($table->totals)
                <tr class="total">
                    @foreach ($table->columns as $column)
                        <td class="{{ $table->isRightAligned($column['type']) ? 'right' : '' }}">{{ array_key_exists($column['key'], $table->totals) ? App\Services\Reports\ReportTable::display($column, $table->totals[$column['key']]) : '' }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @if ($table->summary)
        <table class="summary">
            @foreach ($table->summary as $item)
                <tr><td>{{ $item['label'] }}</td><td><strong>{{ App\Services\Reports\ReportTable::displayAs($item['type'], $item['value']) }}</strong></td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
