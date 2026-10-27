@extends('layouts.owner')

@section('title', 'Verifikasi bukti transfer')

@section('content')
    @php($tz = auth()->user()->tenant->timezone)

    <nav class="flex flex-wrap gap-2" aria-label="Filter bukti">
        @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'] as $key => $label)
            <a href="{{ route('owner.proofs.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
               class="px-3 py-1.5 rounded-full font-label-md {{ $tab === $key ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                {{ $label }} <span class="angka">({{ $counts[$key] }})</span>
            </a>
        @endforeach
    </nav>

    @forelse ($proofs as $proof)
        @php($file = $proof->attachments->first())
        @php($remaining = $proof->invoice->remaining())
        <x-panel>
            <div class="flex flex-wrap items-start justify-between gap-space-md">
                <div>
                    <div class="font-label-lg">
                        {{ $proof->resident->name }} · Kamar <span class="angka">{{ $proof->invoice->lease->room->number }}</span>
                    </div>
                    <div class="font-body-sm text-secondary">
                        Tagihan <a class="text-primary hover:underline angka" href="{{ route('owner.invoices.show', $proof->invoice_id) }}">{{ $proof->invoice->number }}</a>
                        · diunggah {{ $proof->created_at->timezone($tz)->translatedFormat('j M Y H:i') }}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-status-badge :tone="$proof->status->tone()">{{ $proof->status->label() }}</x-status-badge>
                    @if ($proof->isStale())
                        <x-status-badge tone="danger">Menunggu lebih dari {{ App\Models\PaymentProof::STALE_AFTER_DAYS }} hari</x-status-badge>
                    @endif
                </div>
            </div>

            <dl class="mt-space-md grid grid-cols-2 lg:grid-cols-4 gap-x-space-lg gap-y-space-sm text-body-md">
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Nominal diklaim</dt><dd class="angka font-label-lg">{{ App\Support\Rupiah::format($proof->amount) }}</dd></div>
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Sisa tagihan</dt><dd class="angka">{{ App\Support\Rupiah::format($remaining) }}</dd></div>
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Tanggal transfer</dt><dd class="angka">{{ $proof->paid_on?->translatedFormat('j M Y') ?? '—' }}</dd></div>
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Berkas</dt>
                    <dd>@if ($file)<a class="text-primary hover:underline" href="{{ $file->temporaryUrl() }}" target="_blank" rel="noopener">Lihat bukti</a>@else — @endif</dd></div>
                @if ($proof->sender_note)
                    <div class="col-span-2 lg:col-span-4"><dt class="text-secondary font-label-sm uppercase tracking-wider">Catatan pengirim</dt><dd>{{ $proof->sender_note }}</dd></div>
                @endif
            </dl>

            @if ($proof->status->value === 'pending')
                @can('review', $proof)
                    <div class="mt-space-md grid grid-cols-1 lg:grid-cols-2 gap-space-md border-t border-outline-variant/40 pt-space-md">
                        <form method="POST" action="{{ route('owner.proofs.approve', $proof) }}" class="flex flex-col gap-space-sm">
                            @csrf
                            <x-input name="amount" inputmode="numeric" label="Nominal disetujui (Rp)" hint="Kosongkan untuk menyetujui sesuai klaim. Boleh lebih kecil, tidak boleh lebih besar." />
                            <div><x-button type="submit">Setujui dan catat pembayaran</x-button></div>
                        </form>
                        <form method="POST" action="{{ route('owner.proofs.reject', $proof) }}" class="flex flex-col gap-space-sm">
                            @csrf
                            <x-input name="reason" label="Alasan penolakan (wajib)" />
                            <div><x-button type="submit" variant="danger">Tolak</x-button></div>
                        </form>
                    </div>
                @endcan
            @elseif ($proof->status->value === 'approved')
                <p class="mt-space-md text-body-md">Disetujui oleh {{ $proof->reviewer?->name ?? '—' }}. Kuitansi
                    @if ($proof->payment)<a class="text-primary hover:underline angka" href="{{ route('owner.payments.show', $proof->payment_id) }}">{{ $proof->payment->receipt_number }}</a>@endif
                </p>
            @else
                <p class="mt-space-md text-body-md">Ditolak oleh {{ $proof->reviewer?->name ?? '—' }}. Alasan: {{ $proof->reject_reason }}</p>
            @endif
        </x-panel>
    @empty
        <x-panel><p class="text-on-surface-variant">Tidak ada bukti transfer di daftar ini.</p></x-panel>
    @endforelse

    {{ $proofs->links() }}
@endsection
