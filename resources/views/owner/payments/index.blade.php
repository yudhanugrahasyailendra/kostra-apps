@extends('layouts.owner')

@section('title', 'Pembayaran')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-space-sm">
        <p class="text-secondary">Uang yang diterima di luar sistem. Kostra hanya mencatat; tidak menahan atau menyalurkan dana.</p>
        @can('record', App\Models\Payment::class)
            <x-button :href="route('owner.payments.create')">Catat pembayaran</x-button>
        @endcan
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Pemasukan diterima</div>
            <div class="font-currency-stat text-currency-stat angka">{{ App\Support\Rupiah::format($summary['received']) }}</div>
            <div class="font-body-sm text-secondary">{{ $summary['active'] }} pembayaran aktif</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Dibatalkan</div>
            <div class="font-currency-stat text-currency-stat angka">{{ $summary['voided'] }}</div>
            <div class="font-body-sm text-secondary">tidak dihitung sebagai pemasukan</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Antrean bukti</div>
            <div class="font-currency-stat text-currency-stat angka">{{ App\Models\PaymentProof::query()->pending()->count() }}</div>
            <a class="font-body-sm text-primary hover:underline" href="{{ route('owner.proofs.index') }}">Buka antrean verifikasi</a>
        </div>
    </div>

    <form method="GET" action="{{ route('owner.payments.index') }}" class="panel p-space-md grid grid-cols-2 lg:grid-cols-5 gap-space-md items-end">
        <x-select name="period" label="Bulan bayar">
            <option value="all" @selected($period === 'all')>Semua periode</option>
            @foreach ($months as $month)
                <option value="{{ $month->format('Y-m') }}" @selected($period === $month->format('Y-m'))>{{ $month->locale('id')->translatedFormat('F Y') }}</option>
            @endforeach
        </x-select>
        <x-select name="method" label="Metode" placeholder="Semua metode">
            @foreach (App\Enums\PaymentMethod::cases() as $case)
                <option value="{{ $case->value }}" @selected($method === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select>
        <x-select name="status" label="Status" placeholder="Semua status">
            <option value="active" @selected($status === 'active')>Aktif</option>
            <option value="voided" @selected($status === 'voided')>Dibatalkan</option>
        </x-select>
        <x-input name="q" label="Cari kuitansi, referensi, penghuni" :value="$q" />
        <div class="flex gap-2">
            <x-button type="submit">Terapkan</x-button>
            <x-button :href="route('owner.payments.index')" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    <x-panel>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Kuitansi</th>
                        <th class="p-3">Tanggal</th>
                        <th class="p-3">Penghuni</th>
                        <th class="p-3 hidden md:table-cell">Tagihan</th>
                        <th class="p-3">Metode</th>
                        <th class="p-3 text-right">Jumlah</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr class="border-t border-outline-variant/40 hover:bg-surface-container-low/50">
                            <td class="p-3 angka"><a href="{{ route('owner.payments.show', $payment) }}" class="font-label-lg text-primary hover:underline">{{ $payment->receipt_number }}</a></td>
                            <td class="p-3 angka">{{ $payment->paid_on->translatedFormat('j M Y') }}</td>
                            <td class="p-3">{{ $payment->resident->name }}</td>
                            <td class="p-3 hidden md:table-cell font-body-sm angka">{{ $payment->allocations->map(fn ($a) => $a->invoice->number)->implode(', ') }}</td>
                            <td class="p-3">{{ $payment->method->label() }}</td>
                            <td class="p-3 angka text-right">{{ App\Support\Rupiah::format($payment->amount) }}</td>
                            <td class="p-3"><x-status-badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-status-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4 text-on-surface-variant">Belum ada pembayaran yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{ $payments->links() }}
@endsection
