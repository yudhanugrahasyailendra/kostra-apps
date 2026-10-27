@extends('layouts.owner')

@section('title', 'Pembayaran '.$payment->receipt_number)

@section('content')
    @php($tz = auth()->user()->tenant->timezone)

    <x-panel>
        <div class="flex flex-wrap items-start justify-between gap-space-md">
            <div>
                <h2 class="font-headline-lg text-headline-lg angka">{{ $payment->receipt_number }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    <x-status-badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-status-badge>
                    <x-status-badge tone="neutral">{{ $payment->method->label() }}</x-status-badge>
                </div>
            </div>
            <div class="flex gap-2">
                <x-button :href="route('owner.payments.receipt', $payment)" variant="secondary" target="_blank">Kuitansi</x-button>
                <x-button :href="route('owner.payments.index')" variant="secondary">Semua pembayaran</x-button>
            </div>
        </div>

        <dl class="mt-space-md grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-space-sm text-body-md">
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Penghuni</dt>
                <dd><a class="text-primary hover:underline" href="{{ route('owner.residents.show', $payment->resident_id) }}">{{ $payment->resident->name }}</a></dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Jumlah diterima</dt><dd class="angka font-label-lg">{{ App\Support\Rupiah::format($payment->amount) }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Tanggal bayar</dt><dd class="angka">{{ $payment->paid_on->translatedFormat('j F Y') }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Rekening tujuan</dt><dd>{{ $payment->account?->label() ?: '—' }}</dd></div>
            @if ($payment->reference)
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Referensi</dt><dd class="angka">{{ $payment->reference }}</dd></div>
            @endif
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Dicatat oleh</dt><dd>{{ $payment->recorder?->name ?? '—' }}, {{ $payment->created_at->timezone($tz)->translatedFormat('j M Y H:i') }}</dd></div>
            @if ($payment->notes)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Catatan</dt><dd>{{ $payment->notes }}</dd></div>
            @endif
            @if ($payment->isVoided())
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Dibatalkan</dt>
                    <dd>{{ $payment->voided_at?->timezone($tz)->translatedFormat('j F Y H:i') }} oleh {{ $payment->voider?->name ?? '—' }} · {{ $payment->void_reason }}</dd></div>
            @endif
        </dl>
    </x-panel>

    <x-panel title="Dialokasikan ke tagihan">
        <table class="table">
            <thead>
                <tr><th>Tagihan</th><th>Status tagihan</th><th class="text-right">Alokasi</th></tr>
            </thead>
            <tbody>
                @foreach ($payment->allocations as $allocation)
                    <tr>
                        <td class="angka"><a class="text-primary hover:underline" href="{{ route('owner.invoices.show', $allocation->invoice_id) }}">{{ $allocation->invoice->number }}</a></td>
                        <td><x-status-badge :tone="$allocation->invoice->status->tone()">{{ $allocation->invoice->status->label() }}</x-status-badge></td>
                        <td class="angka text-right">{{ App\Support\Rupiah::format($allocation->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-outline-variant">
                <tr><td class="font-label-lg" colspan="2">Total</td><td class="angka text-right font-label-lg">{{ App\Support\Rupiah::format($payment->amount) }}</td></tr>
            </tfoot>
        </table>
    </x-panel>

    @if (! $payment->isVoided())
        @can('void', $payment)
            <x-panel title="Batalkan pembayaran">
                <p class="text-body-md text-on-surface-variant mb-space-md">Pembayaran tidak dihapus. Setelah dibatalkan, tagihan terkait kembali menunggu pembayaran dan kuitansinya ditandai dibatalkan.</p>
                <form method="POST" action="{{ route('owner.payments.void', $payment) }}" class="flex flex-col gap-space-md" onsubmit="return confirm('Batalkan pembayaran ini?')">
                    @csrf
                    <x-input name="reason" label="Alasan pembatalan (wajib)" required />
                    <div><x-button type="submit" variant="danger">Batalkan pembayaran</x-button></div>
                </form>
            </x-panel>
        @endcan
    @endif
@endsection
