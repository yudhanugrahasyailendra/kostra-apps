@extends('layouts.owner')

@section('title', 'Tagihan '.$invoice->number)

@section('content')
    @php
        $tz = auth()->user()->tenant->timezone;
        $isVoid = $invoice->status->value === 'void';
    @endphp

    <x-panel>
        <div class="flex flex-wrap items-start justify-between gap-space-md">
            <div>
                <h2 class="font-headline-lg text-headline-lg angka">{{ $invoice->number }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    <x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge>
                    <x-status-badge tone="neutral">{{ $invoice->type->label() }}</x-status-badge>
                </div>
            </div>
            <x-button :href="route('owner.invoices.index')" variant="secondary">Semua tagihan</x-button>
        </div>

        <dl class="mt-space-md grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-space-sm text-body-md">
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Penghuni</dt>
                <dd><a class="text-primary hover:underline" href="{{ route('owner.residents.show', $invoice->resident_id) }}">{{ $invoice->resident->name }}</a></dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Kamar</dt>
                <dd><a class="text-primary hover:underline" href="{{ route('owner.leases.show', $invoice->lease_id) }}">Kamar <span class="angka">{{ $invoice->lease->room->number }}</span></a> · {{ $invoice->property->name }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Tanggal terbit</dt><dd class="angka">{{ $invoice->issue_date->translatedFormat('j F Y') }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Jatuh tempo</dt><dd class="angka">{{ $invoice->due_date->translatedFormat('j F Y') }}</dd></div>
            @if ($invoice->period_start)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Periode yang ditagih</dt>
                    <dd class="angka">{{ $invoice->period_start->translatedFormat('j F Y') }} s/d {{ $invoice->period_end->translatedFormat('j F Y') }}</dd></div>
            @endif
            @if ($invoice->notes)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Catatan</dt><dd>{{ $invoice->notes }}</dd></div>
            @endif
            @if ($isVoid)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Dibatalkan</dt>
                    <dd>{{ $invoice->voided_at?->timezone($tz)->translatedFormat('j F Y') }} · {{ $invoice->void_reason }}</dd></div>
            @endif
        </dl>
    </x-panel>

    <x-panel title="Rincian">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr><th class="p-3">Item</th><th class="p-3">Kuantitas</th><th class="p-3">Harga satuan</th><th class="p-3 text-right">Jumlah</th></tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr class="border-t border-outline-variant/40">
                            <td class="p-3">{{ $item->description }} <span class="text-secondary font-body-sm">({{ $item->type->label() }})</span></td>
                            <td class="p-3 angka">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                            <td class="p-3 angka">{{ App\Support\Rupiah::format($item->unit_price) }}</td>
                            <td class="p-3 angka text-right">{{ App\Support\Rupiah::format($item->amount) }}</td>
                        </tr>
                    @endforeach
                    @foreach ($invoice->adjustments as $adjustment)
                        <tr class="border-t border-outline-variant/40">
                            <td class="p-3" colspan="3">
                                Penyesuaian: {{ $adjustment->reason }}
                                <span class="text-secondary font-body-sm">· {{ $adjustment->creator?->name ?? '—' }}, {{ $adjustment->created_at->timezone($tz)->translatedFormat('j M Y') }}</span>
                            </td>
                            <td class="p-3 angka text-right {{ $adjustment->amount < 0 ? 'text-error' : '' }}">
                                {{ $adjustment->amount < 0 ? '- ' : '+ ' }}{{ App\Support\Rupiah::format(abs($adjustment->amount)) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-outline-variant">
                    <tr><td class="p-3 font-label-lg" colspan="3">Total</td><td class="p-3 angka text-right font-label-lg">{{ App\Support\Rupiah::format($invoice->total) }}</td></tr>
                    <tr><td class="p-3" colspan="3">Sudah dibayar</td><td class="p-3 angka text-right">{{ App\Support\Rupiah::format($invoice->paid_total) }}</td></tr>
                    <tr><td class="p-3 font-label-lg" colspan="3">Sisa tagihan</td><td class="p-3 angka text-right font-label-lg">{{ App\Support\Rupiah::format($invoice->remaining()) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-space-sm text-body-sm text-secondary">Jumlah item yang sudah terbit tidak diubah. Koreksi dicatat sebagai penyesuaian.</p>
    </x-panel>

    <x-panel title="Pembayaran">
        @forelse ($invoice->allocations as $allocation)
            <a href="{{ route('owner.payments.show', $allocation->payment_id) }}" class="flex flex-wrap items-center justify-between gap-2 py-space-sm border-b border-outline-variant/40 last:border-0 hover:bg-surface-container-low/50">
                <div>
                    <div class="font-label-lg angka">{{ $allocation->payment->receipt_number }}</div>
                    <div class="font-body-sm text-secondary">{{ $allocation->payment->paid_on->translatedFormat('j M Y') }} · {{ $allocation->payment->method->label() }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="angka">{{ App\Support\Rupiah::format($allocation->amount) }}</span>
                    <x-status-badge :tone="$allocation->payment->status->tone()">{{ $allocation->payment->status->label() }}</x-status-badge>
                </div>
            </a>
        @empty
            <p class="text-on-surface-variant">Belum ada pembayaran.</p>
        @endforelse

        @if (in_array($invoice->status->value, ['issued', 'partial', 'overdue'], true))
            @can('record', App\Models\Payment::class)
                <div class="mt-space-md">
                    <x-button :href="route('owner.payments.create', ['invoice_id' => $invoice->id])">Catat pembayaran</x-button>
                </div>
            @endcan
        @endif
    </x-panel>

    <x-panel title="Bukti transfer">
        @forelse ($invoice->proofs as $proof)
            <div class="py-space-sm border-b border-outline-variant/40 last:border-0 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="angka font-label-lg">{{ App\Support\Rupiah::format($proof->amount) }}</div>
                    <div class="font-body-sm text-secondary">{{ $proof->created_at->timezone($tz)->translatedFormat('j M Y H:i') }}@if ($proof->sender_note) · {{ $proof->sender_note }}@endif</div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($proof->attachments->first())
                        <a class="text-primary hover:underline" href="{{ $proof->attachments->first()->temporaryUrl() }}" target="_blank" rel="noopener">Lihat</a>
                    @endif
                    <x-status-badge :tone="$proof->status->tone()">{{ $proof->status->label() }}</x-status-badge>
                </div>
            </div>
        @empty
            <p class="text-on-surface-variant">Belum ada bukti transfer.</p>
        @endforelse

        @if (in_array($invoice->status->value, ['issued', 'partial', 'overdue'], true))
            @can('submit', App\Models\PaymentProof::class)
                <form method="POST" action="{{ route('owner.invoices.proofs.store', $invoice) }}" enctype="multipart/form-data" class="mt-space-md flex flex-col gap-space-md border-t border-outline-variant/40 pt-space-md">
                    @csrf
                    <p class="text-body-sm text-secondary">Catat bukti transfer yang diterima dari penghuni. Tagihan baru berubah setelah bukti disetujui di antrean verifikasi.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
                        <x-input name="amount" inputmode="numeric" label="Nominal (Rp)" :value="number_format($invoice->remaining(), 0, ',', '.')" required />
                        <x-input name="paid_on" type="date" label="Tanggal transfer" max="{{ $today->toDateString() }}" />
                        <x-input name="sender_note" label="Catatan pengirim" />
                    </div>
                    <x-input name="file" type="file" accept=".jpg,.jpeg,.png,.pdf" label="Bukti transfer (JPG, PNG, atau PDF, maksimal 5 MB)" required />
                    <div><x-button type="submit" variant="secondary">Catat bukti</x-button></div>
                </form>
            @endcan
        @endif
    </x-panel>

    @unless ($isVoid)
        @can('adjust', $invoice)
            <x-panel title="Penyesuaian">
                <form method="POST" action="{{ route('owner.invoices.adjust', $invoice) }}" class="flex flex-col gap-space-md">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
                        <x-select name="direction" label="Jenis">
                            <option value="decrease" @selected(old('direction') === 'decrease')>Kurangi tagihan (potongan)</option>
                            <option value="increase" @selected(old('direction') === 'increase')>Tambah tagihan</option>
                        </x-select>
                        <x-input name="amount" inputmode="numeric" label="Nominal (Rp)" required />
                        <x-input name="reason" label="Alasan (wajib)" required />
                    </div>
                    <div><x-button type="submit" variant="secondary">Catat penyesuaian</x-button></div>
                </form>
            </x-panel>
        @endcan

        @can('void', $invoice)
            <x-panel title="Batalkan tagihan">
                <p class="text-body-md text-on-surface-variant mb-space-md">Tagihan batal tidak dihapus dan nomornya tetap tercatat. Tagihan lunas atau yang sudah punya pembayaran tidak bisa dibatalkan.</p>
                <form method="POST" action="{{ route('owner.invoices.void', $invoice) }}" class="flex flex-col gap-space-md" onsubmit="return confirm('Batalkan tagihan ini?')">
                    @csrf
                    <x-input name="reason" label="Alasan pembatalan (wajib)" required />
                    <div><x-button type="submit" variant="danger">Batalkan tagihan</x-button></div>
                </form>
            </x-panel>
        @endcan
    @endunless
@endsection
