@extends('layouts.owner')

@section('title', $table->title)

@section('content')
    <x-page-header eyebrow="Keuangan kos" title="Laporan Keuangan" subtitle="Pemasukan, tunggakan, okupansi, dan rekap bulanan. Unduh sebagai Excel atau PDF.">
        @can('finance.view')
            <x-button :href="route('owner.expenses.index')" variant="secondary"><span class="material-symbols-outlined text-base" aria-hidden="true">account_balance_wallet</span> Buku Pengeluaran</x-button>
        @endcan
    </x-page-header>

    <nav class="flex flex-wrap gap-1 p-1.5 rounded-xl bg-surface-container-low w-fit max-w-full" aria-label="Jenis laporan">
        @foreach ($reports as $key => $label)
            <a href="{{ route('owner.reports.show', $key) }}" @if ($key === $report) aria-current="page" @endif
               class="px-4 py-2 rounded-lg font-label-md text-label-md transition-colors {{ $key === $report ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('owner.reports.show', $report) }}" class="panel p-space-md grid grid-cols-2 lg:grid-cols-5 gap-space-md items-end">
        <x-select name="property_id" label="Properti" placeholder="Semua properti">
            @foreach ($properties as $property)
                <option value="{{ $property->id }}" @selected($propertyId === $property->id)>{{ $property->name }}</option>
            @endforeach
        </x-select>

        @if (in_array($report, ['pemasukan', 'rekap'], true))
            <x-input name="month" type="month" :label="$report === 'rekap' ? 'Bulan terakhir' : 'Bulan'" :value="$month" />
        @endif
        @if ($report === 'pemasukan')
            <x-input name="from" type="date" label="Atau dari tanggal" :value="$from" />
            <x-input name="to" type="date" label="Sampai tanggal" :value="$to" />
        @endif
        @if ($report === 'okupansi')
            <x-input name="date" type="date" label="Per tanggal" :value="$asOf" />
        @endif
        @if ($report === 'tunggakan')
            <x-select name="sort" label="Urutkan">
                <option value="amount" @selected($sort === 'amount')>Jumlah terbesar</option>
                <option value="age" @selected($sort === 'age')>Umur terlama</option>
            </x-select>
        @endif

        <div class="flex gap-2">
            <x-button type="submit">Terapkan</x-button>
            <x-button :href="route('owner.reports.show', $report)" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    <x-panel :title="$table->title">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-1 mb-space-md font-body-md">
            @foreach ($table->meta as $label => $value)
                <div class="flex gap-2"><dt class="text-secondary">{{ $label }}:</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>

        @if ($table->summary)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md mb-space-md">
                @foreach ($table->summary as $item)
                    <div class="rounded-xl bg-surface-container-low p-space-md">
                        <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">{{ $item['label'] }}</div>
                        <div class="font-currency-stat text-currency-stat angka">{{ App\Services\Reports\ReportTable::displayAs($item['type'], $item['value']) }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap gap-2 mb-space-md">
            <x-button :href="route('owner.reports.show', ['report' => $report] + $query + ['format' => 'xlsx'])" variant="secondary">Unduh Excel</x-button>
            <x-button :href="route('owner.reports.show', ['report' => $report] + $query + ['format' => 'pdf'])" variant="secondary">Unduh PDF</x-button>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        @foreach ($table->columns as $column)
                            <th class="{{ $table->isRightAligned($column['type']) ? 'text-right' : '' }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($table->rows as $row)
                        <tr>
                            @foreach ($table->columns as $column)
                                <td class="{{ in_array($column['type'], ['money', 'int', 'percent', 'decimal', 'date', 'month'], true) ? 'angka' : '' }} {{ $table->isRightAligned($column['type']) ? 'text-right' : '' }}">{{ App\Services\Reports\ReportTable::display($column, $row[$column['key']] ?? null) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($table->columns) }}" class="p-4 text-on-surface-variant">Tidak ada data untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
                @if ($table->totals)
                    <tfoot class="border-t-2 border-outline-variant">
                        <tr class="font-label-lg">
                            @foreach ($table->columns as $column)
                                <td class="{{ $table->isRightAligned($column['type']) ? 'text-right angka' : '' }}">{{ array_key_exists($column['key'], $table->totals) ? App\Services\Reports\ReportTable::display($column, $table->totals[$column['key']]) : '' }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-panel>
@endsection
