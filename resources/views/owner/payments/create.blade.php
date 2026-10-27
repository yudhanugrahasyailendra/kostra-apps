@extends('layouts.owner')

@section('title', 'Catat pembayaran')

@section('content')
    @if (! $resident)
        <x-panel title="Pilih penghuni">
            @forelse ($residents as $item)
                <a href="{{ route('owner.payments.create', ['resident_id' => $item->id]) }}"
                   class="flex items-center justify-between py-space-sm border-b border-outline-variant/40 last:border-0 hover:bg-surface-container-low/50">
                    <span class="font-label-lg">{{ $item->name }}</span>
                    <span class="font-body-sm text-secondary angka">{{ $item->phone }}</span>
                </a>
            @empty
                <p class="text-on-surface-variant">Tidak ada tagihan yang menunggu pembayaran.</p>
            @endforelse
        </x-panel>
    @else
        <x-panel :title="'Pembayaran dari '.$resident->name">
            @php($allocationErrors = collect($errors->get('allocations*'))->flatten()->unique())
            @if ($allocationErrors->isNotEmpty())
                <div class="mb-space-md rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm font-label-md" role="alert">
                    @foreach ($allocationErrors as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            @if ($invoices->isEmpty())
                <p class="text-on-surface-variant">Penghuni ini tidak punya tagihan yang menunggu pembayaran.</p>
            @else
                <form method="POST" action="{{ route('owner.payments.store') }}" class="flex flex-col gap-space-md">
                    @csrf
                    <input type="hidden" name="resident_id" value="{{ $resident->id }}">

                    <fieldset class="flex flex-col gap-space-sm">
                        <legend class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-1">Tagihan yang dibayar</legend>
                        <p class="font-body-sm text-body-sm text-secondary">Isi nominal untuk tagihan yang dibayar; kosongkan yang tidak. Nominal tidak boleh melebihi sisa tagihan, dan total pembayaran adalah jumlah semua nominal.</p>
                        @foreach ($invoices as $invoice)
                            <div class="grid grid-cols-12 gap-space-sm items-center border-b border-outline-variant/40 pb-space-sm">
                                <div class="col-span-12 sm:col-span-7">
                                    <div class="font-label-lg angka">{{ $invoice->number }}</div>
                                    <div class="font-body-sm text-secondary">
                                        Kamar <span class="angka">{{ $invoice->lease->room->number }}</span> · jatuh tempo <span class="angka">{{ $invoice->due_date->translatedFormat('j M Y') }}</span>
                                        · <x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge>
                                    </div>
                                    <div class="font-body-sm">Sisa <span class="angka font-label-lg">{{ App\Support\Rupiah::format($invoice->remaining()) }}</span> dari <span class="angka">{{ App\Support\Rupiah::format($invoice->total) }}</span></div>
                                </div>
                                <div class="col-span-12 sm:col-span-5">
                                    <label class="sr-only" for="alloc-{{ $invoice->id }}">Nominal dibayar untuk {{ $invoice->number }}</label>
                                    <input id="alloc-{{ $invoice->id }}" type="text" inputmode="numeric" name="allocations[{{ $invoice->id }}]"
                                           value="{{ old("allocations.$invoice->id", $preselected === $invoice->id ? number_format($invoice->remaining(), 0, ',', '.') : '') }}"
                                           placeholder="Nominal dibayar (Rp)"
                                           class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 h-12 lg:h-10 angka focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                    @error("allocations.$invoice->id")<p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endforeach
                    </fieldset>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md">
                        <x-input name="paid_on" type="date" label="Tanggal bayar" :value="$today" max="{{ $today }}" required />
                        <x-select name="method" label="Metode">
                            @foreach ($methods as $case)
                                <option value="{{ $case->value }}" @selected(old('method', 'transfer') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </x-select>
                        <x-select name="payment_account_id" label="Rekening tujuan" placeholder="Tanpa rekening (tunai)">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((int) old('payment_account_id') === $account->id)>{{ $account->label() }}@if ($account->is_default) (utama)@endif</option>
                            @endforeach
                        </x-select>
                    </div>
                    <x-input name="reference" label="Nomor referensi transfer" />
                    <x-input name="notes" label="Catatan" />

                    <div class="flex gap-2">
                        <x-button type="submit">Catat pembayaran</x-button>
                        <x-button :href="route('owner.payments.create')" variant="secondary">Ganti penghuni</x-button>
                    </div>
                </form>
            @endif
        </x-panel>
    @endif
@endsection
