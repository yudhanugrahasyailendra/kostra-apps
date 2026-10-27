@extends('layouts.owner')

@section('title', 'Tagihan')

@section('content')
    @php
        $params = fn (array $extra = []) => array_filter(array_merge(['tab' => $tab, 'period' => $period, 'q' => $q, 'property_id' => $propertyId], $extra));
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Total tagihan terbit</div>
            <div class="font-currency-stat text-currency-stat angka">{{ App\Support\Rupiah::format($summary['issued']) }}</div>
            <div class="font-body-sm text-secondary">{{ $counts['all'] - $counts['void'] }} tagihan, tanpa yang batal</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Sudah terbayar</div>
            <div class="font-currency-stat text-currency-stat angka">{{ App\Support\Rupiah::format($summary['collected']) }}</div>
            <div class="font-body-sm text-secondary">{{ $counts['paid'] }} tagihan lunas</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Terlambat</div>
            <div class="font-currency-stat text-currency-stat angka {{ $summary['overdue'] > 0 ? 'text-error' : '' }}">{{ App\Support\Rupiah::format($summary['overdue']) }}</div>
            <div class="font-body-sm text-secondary">{{ $counts['overdue'] }} tagihan lewat jatuh tempo</div>
        </div>
    </div>

    <nav class="flex flex-wrap gap-2" aria-label="Filter status tagihan">
        @foreach (['all' => 'Semua', 'issued' => 'Belum dibayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', 'overdue' => 'Terlambat', 'void' => 'Batal'] as $key => $label)
            <a href="{{ route('owner.invoices.index', $params(['tab' => $key])) }}" @if ($tab === $key) aria-current="page" @endif
               class="px-3 py-1.5 rounded-full font-label-md {{ $tab === $key ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                {{ $label }} <span class="angka">({{ $counts[$key] }})</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('owner.invoices.index') }}" class="panel p-space-md grid grid-cols-1 sm:grid-cols-4 gap-space-md items-end">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-select name="period" label="Bulan terbit">
            <option value="all" @selected($period === 'all')>Semua periode</option>
            @foreach ($months as $month)
                <option value="{{ $month->format('Y-m') }}" @selected($period === $month->format('Y-m'))>{{ $month->locale('id')->translatedFormat('F Y') }}</option>
            @endforeach
        </x-select>
        <x-select name="property_id" label="Properti" placeholder="Semua properti">
            @foreach ($properties as $property)
                <option value="{{ $property->id }}" @selected($propertyId == $property->id)>{{ $property->name }}</option>
            @endforeach
        </x-select>
        <x-input name="q" label="Cari nomor, penghuni, atau kamar" :value="$q" />
        <div class="flex gap-2">
            <x-button type="submit">Terapkan</x-button>
            <x-button :href="route('owner.invoices.index', ['tab' => $tab])" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    <x-panel>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Nomor</th>
                        <th class="p-3">Penghuni dan kamar</th>
                        <th class="p-3 hidden md:table-cell">Komponen</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">Jatuh tempo</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="border-t border-outline-variant/40 hover:bg-surface-container-low/50">
                            <td class="p-3 angka">
                                <a href="{{ route('owner.invoices.show', $invoice) }}" class="font-label-lg text-primary hover:underline">{{ $invoice->number }}</a>
                                <div class="font-body-sm text-secondary">{{ $invoice->type->label() }}</div>
                            </td>
                            <td class="p-3">
                                {{ $invoice->resident->name }}
                                <div class="font-body-sm text-secondary">Kamar <span class="angka">{{ $invoice->lease->room->number }}</span></div>
                            </td>
                            <td class="p-3 hidden md:table-cell font-body-sm">
                                @foreach ($invoice->items->take(2) as $item)
                                    <div>{{ $item->description }}</div>
                                @endforeach
                                @if ($invoice->items->count() > 2)
                                    <div class="text-secondary">+{{ $invoice->items->count() - 2 }} item lain</div>
                                @endif
                            </td>
                            <td class="p-3 angka">
                                {{ App\Support\Rupiah::format($invoice->total) }}
                                @if ($invoice->paid_total > 0 && $invoice->status->value !== 'paid')
                                    <div class="font-body-sm text-secondary">sisa {{ App\Support\Rupiah::format($invoice->remaining()) }}</div>
                                @endif
                            </td>
                            <td class="p-3 angka">{{ $invoice->due_date->translatedFormat('j M Y') }}</td>
                            <td class="p-3"><x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4 text-on-surface-variant">Tidak ada tagihan yang cocok. Tagihan bulanan terbit otomatis pada tanggal terbit yang diatur di Pengaturan > Penagihan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{ $invoices->links() }}
@endsection
