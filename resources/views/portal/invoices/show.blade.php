@extends('layouts.portal')

@section('title', 'Tagihan '.$invoice->number)

@section('content')
    <a href="{{ route('portal.invoices.index', ['token' => $portalToken]) }}" class="text-primary">&larr; Semua tagihan</a>

    <section class="panel p-space-md">
        <div class="flex items-start justify-between gap-space-sm">
            <div class="min-w-0">
                <h1 class="font-headline-md text-headline-md angka truncate">{{ $invoice->number }}</h1>
                <div class="font-body-sm text-secondary angka">Jatuh tempo {{ $invoice->due_date->translatedFormat('j F Y') }} · Kamar {{ $invoice->lease?->room?->number }}</div>
            </div>
            <x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge>
        </div>

        <table class="w-full mt-space-md text-left">
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2 text-right angka">{{ App\Support\Rupiah::format($item->amount) }}</td>
                    </tr>
                @endforeach
                @if ($invoice->adjustment_total !== 0)
                    <tr>
                        <td class="py-2">Penyesuaian</td>
                        <td class="py-2 text-right angka">{{ $invoice->adjustment_total < 0 ? '−' : '' }}{{ App\Support\Rupiah::format(abs($invoice->adjustment_total)) }}</td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-outline-variant font-label-lg"><td class="py-2">Total</td><td class="py-2 text-right angka">{{ App\Support\Rupiah::format($invoice->total) }}</td></tr>
                <tr><td class="py-1 text-secondary">Sudah dibayar</td><td class="py-1 text-right angka">{{ App\Support\Rupiah::format($invoice->paid_total) }}</td></tr>
                <tr class="font-label-lg"><td class="py-1">Sisa</td><td class="py-1 text-right angka" data-stat="remaining">{{ App\Support\Rupiah::format($invoice->remaining()) }}</td></tr>
            </tfoot>
        </table>
    </section>

    @if ($canPay)
        <section class="panel p-space-md flex flex-col gap-space-md">
            <h2 class="font-headline-md text-headline-md">Cara membayar</h2>
            <p class="font-body-sm text-secondary">Transfer ke salah satu rekening di bawah, lalu unggah bukti transfer. Kostra hanya mencatat; uang Anda tidak melewati Kostra.</p>

            @forelse ($accounts as $account)
                <div class="rounded-xl border border-outline-variant/60 p-space-md flex flex-col gap-space-sm">
                    @if ($account->type === App\Enums\PaymentAccountType::Qris)
                        <div class="font-label-lg">{{ $account->label() }}</div>
                        @if ($account->qrisImage)
                            <img src="{{ route('portal.accounts.qris', ['token' => $portalToken, 'account' => $account->id]) }}" alt="Kode QRIS {{ $account->label() }}" class="max-h-64 mx-auto rounded-lg">
                        @endif
                    @else
                        <div class="font-label-lg">{{ $account->bank_name }}</div>
                        <div class="font-currency-stat text-currency-stat angka break-all">{{ $account->account_number }}</div>
                        <div class="font-body-sm text-secondary">a.n. {{ $account->account_name }}</div>
                        <button type="button" class="btn btn-secondary" data-copy="{{ $account->account_number }}" data-copied="Tersalin">Salin nomor rekening</button>
                    @endif
                    @if ($account->instructions)
                        <p class="font-body-sm whitespace-pre-line">{{ $account->instructions }}</p>
                    @endif
                </div>
            @empty
                <p class="text-on-surface-variant">Belum ada rekening pembayaran. Hubungi pengelola kos.</p>
            @endforelse
        </section>

        <section class="panel p-space-md">
            <h2 class="font-headline-md text-headline-md mb-space-sm">Unggah bukti transfer</h2>
            <form method="POST" enctype="multipart/form-data" action="{{ route('portal.proofs.store', ['token' => $portalToken, 'invoice' => $invoice->id]) }}" class="flex flex-col gap-space-md">
                @csrf
                <x-input name="amount" label="Nominal yang ditransfer (rupiah)" inputmode="numeric" :value="number_format($invoice->remaining(), 0, ',', '.')" required />
                <x-input name="paid_on" type="date" label="Tanggal transfer (opsional)" :max="$today ?? null" />
                <x-input name="sender_note" label="Catatan (opsional)" maxlength="255" />
                <x-input name="file" type="file" accept=".jpg,.jpeg,.png,.pdf" label="Foto atau PDF bukti (maksimal 5 MB)" required />
                <x-button type="submit">Kirim bukti</x-button>
            </form>
        </section>
    @endif

    @if ($proofs->isNotEmpty())
        <section class="panel p-space-md">
            <h2 class="font-headline-md text-headline-md mb-space-sm">Bukti yang Anda kirim</h2>
            <ul class="flex flex-col gap-space-sm">
                @foreach ($proofs as $proof)
                    <li class="flex items-start justify-between gap-space-sm border-t border-outline-variant/40 pt-space-sm first:border-0 first:pt-0">
                        <div>
                            <div class="angka">{{ App\Support\Rupiah::format($proof->amount) }}</div>
                            <div class="font-body-sm text-secondary angka">{{ $proof->created_at->timezone($portalTenant->timezone)->translatedFormat('j M Y H:i') }}</div>
                            @if ($proof->status === App\Enums\ProofStatus::Rejected && $proof->reject_reason)
                                <div class="font-body-sm text-error">Alasan penolakan: {{ $proof->reject_reason }}</div>
                            @endif
                        </div>
                        <x-status-badge :tone="$proof->status->tone()">{{ $proof->status->label() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        // Tombol salin nomor rekening. Tanpa Clipboard API (HTTP biasa atau peramban lama) dipakai cadangan.
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var text = button.getAttribute('data-copy');
                var done = function () { button.textContent = button.getAttribute('data-copied'); };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done);
                    return;
                }

                var field = document.createElement('textarea');
                field.value = text;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(field);
            });
        });
    </script>
@endpush
