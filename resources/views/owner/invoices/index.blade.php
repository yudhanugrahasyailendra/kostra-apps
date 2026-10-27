@extends('layouts.owner')

@section('title', 'Tagihan')

@section('content')
    @php
        $params = fn (array $extra = []) => array_filter(array_merge(['tab' => $tab, 'period' => $period, 'q' => $q, 'property_id' => $propertyId], $extra));
    @endphp

    <x-page-header eyebrow="Keuangan kos" title="Tagihan & Pembayaran">
        <x-button :href="route('owner.payments.index')" variant="secondary"><span class="material-symbols-outlined text-base" aria-hidden="true">payments</span> Riwayat Pembayaran</x-button>
        @can('create', App\Models\Invoice::class)
            <x-button :href="route('owner.invoices.create')"><span class="material-symbols-outlined text-base" aria-hidden="true">add_circle</span> Buat Tagihan Insidental</x-button>
        @endcan
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
        <x-kpi-card label="Total tagihan terbit" :value="App\Support\Rupiah::format($summary['issued'])" icon="receipt_long" :hint="($counts['all'] - $counts['void']).' tagihan, tanpa yang batal'" />
        <x-kpi-card label="Sudah terbayar" :value="App\Support\Rupiah::format($summary['collected'])" icon="account_balance_wallet" tone="ok" :hint="$counts['paid'].' tagihan lunas'" />
        <x-kpi-card label="Terlambat" :value="App\Support\Rupiah::format($summary['overdue'])" icon="warning" :tone="$summary['overdue'] > 0 ? 'danger' : 'neutral'" :hint="$counts['overdue'].' tagihan lewat jatuh tempo'" />
    </div>

    <nav class="flex flex-wrap gap-1 p-1.5 rounded-xl bg-surface-container-low w-fit max-w-full" aria-label="Filter status tagihan">
        @foreach (['all' => 'Semua', 'issued' => 'Belum dibayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', 'overdue' => 'Terlambat', 'void' => 'Batal'] as $key => $label)
            <a href="{{ route('owner.invoices.index', $params(['tab' => $key])) }}" @if ($tab === $key) aria-current="page" @endif
               class="px-3 py-1.5 rounded-lg font-label-md text-label-md transition-colors {{ $tab === $key ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' }}">
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
            <table class="table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Penghuni dan kamar</th>
                        <th class="hidden md:table-cell">Komponen</th>
                        <th>Total</th>
                        <th>Jatuh tempo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="angka">
                                <a href="{{ route('owner.invoices.show', $invoice) }}" class="font-label-lg text-primary hover:underline">{{ $invoice->number }}</a>
                                <div class="font-body-sm text-secondary">{{ $invoice->type->label() }}</div>
                            </td>
                            <td>
                                {{ $invoice->resident->name }}
                                <div class="font-body-sm text-secondary">Kamar <span class="angka">{{ $invoice->lease->room->number }}</span></div>
                            </td>
                            <td class="hidden md:table-cell font-body-sm">
                                @foreach ($invoice->items->take(2) as $item)
                                    <div>{{ $item->description }}</div>
                                @endforeach
                                @if ($invoice->items->count() > 2)
                                    <div class="text-secondary">+{{ $invoice->items->count() - 2 }} item lain</div>
                                @endif
                            </td>
                            <td class="angka">
                                {{ App\Support\Rupiah::format($invoice->total) }}
                                @if ($invoice->paid_total > 0 && $invoice->status->value !== 'paid')
                                    <div class="font-body-sm text-secondary">sisa {{ App\Support\Rupiah::format($invoice->remaining()) }}</div>
                                @endif
                            </td>
                            <td class="angka">{{ $invoice->due_date->translatedFormat('j M Y') }}</td>
                            <td><x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge></td>
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
