@extends('layouts.owner')

@section('title', 'Kontrak kamar '.$lease->room->number)

@section('content')
    @php
        $tz = auth()->user()->tenant->timezone;
        $days = $lease->daysRemaining($today);
        $isActive = $lease->status->value === 'active';
        $deposit = $lease->deposit;
    @endphp

    <x-panel>
        <div class="flex flex-wrap items-start justify-between gap-space-md">
            <div>
                <h2 class="font-headline-lg text-headline-lg">Kamar <span class="angka">{{ $lease->room->number }}</span></h2>
                <p class="text-secondary">{{ $lease->room->property->name }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <x-status-badge :tone="$lease->status->tone()">{{ $lease->status->label() }}</x-status-badge>
                    @if ($lease->isPastEnd($today))
                        <x-status-badge tone="danger">Lewat masa sewa</x-status-badge>
                    @elseif ($isActive && $days !== null && $days <= App\Models\Lease::EXPIRING_SOON_DAYS)
                        <x-status-badge tone="warn">Berakhir {{ $days === 0 ? 'hari ini' : $days.' hari lagi' }}</x-status-badge>
                    @endif
                    @if ($lease->ended_reason)
                        <x-status-badge tone="neutral">{{ $lease->ended_reason->label() }}</x-status-badge>
                    @endif
                </div>
            </div>
            <x-button :href="route('owner.leases.index')" variant="secondary">Semua kontrak</x-button>
        </div>

        <dl class="mt-space-md grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-space-sm text-body-md">
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Masa kontrak</dt>
                <dd class="angka">{{ $lease->start_date->translatedFormat('j F Y') }} s/d {{ $lease->end_date?->translatedFormat('j F Y') ?? 'tanpa tanggal akhir' }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Harga sewa</dt>
                <dd class="angka">{{ App\Support\Rupiah::format($lease->rent_amount) }} / {{ strtolower($lease->billing_cycle->label()) }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Jatuh tempo</dt>
                <dd>{{ $lease->due_day ? 'Tanggal '.$lease->due_day.' tiap bulan' : 'Mengikuti pengaturan penagihan' }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Check-in</dt>
                <dd>{{ $lease->checked_in_at?->timezone($tz)->translatedFormat('j F Y') ?? '—' }}</dd></div>
            @if ($lease->checked_out_at)
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Check-out</dt>
                    <dd>{{ $lease->checked_out_at->timezone($tz)->translatedFormat('j F Y') }}</dd></div>
            @endif
            <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Penghuni</dt>
                <dd>
                    @foreach ($lease->residents as $resident)
                        <a href="{{ route('owner.residents.show', $resident) }}" class="text-primary hover:underline">{{ $resident->name }}</a>
                        @if ($resident->pivot->is_primary)<x-status-badge tone="ok">Penanggung jawab</x-status-badge>@endif
                        @unless ($loop->last)<br>@endunless
                    @endforeach
                </dd></div>
            @if ($lease->checkin_notes)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Kondisi kamar saat masuk</dt><dd>{{ $lease->checkin_notes }}</dd></div>
            @endif
            @if ($lease->checkout_notes)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Kondisi kamar saat keluar</dt><dd>{{ $lease->checkout_notes }}</dd></div>
            @endif
            @if ($lease->notes)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Catatan</dt><dd>{{ $lease->notes }}</dd></div>
            @endif
            @if ($lease->renewedFrom)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Perpanjangan dari</dt>
                    <dd><a class="text-primary hover:underline" href="{{ route('owner.leases.show', $lease->renewedFrom) }}">Kontrak {{ $lease->renewedFrom->start_date->translatedFormat('j M Y') }} s/d {{ $lease->renewedFrom->end_date?->translatedFormat('j M Y') }}</a></dd></div>
            @endif
            @if ($lease->renewal)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Diperpanjang menjadi</dt>
                    <dd><a class="text-primary hover:underline" href="{{ route('owner.leases.show', $lease->renewal) }}">Kontrak {{ $lease->renewal->start_date->translatedFormat('j M Y') }} s/d {{ $lease->renewal->end_date?->translatedFormat('j M Y') }}</a></dd></div>
            @endif
        </dl>
    </x-panel>

    @if ($deposit)
        <x-panel title="Deposit (tercatat)">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-space-sm text-body-md">
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Nominal</dt><dd class="angka">{{ App\Support\Rupiah::format($deposit->amount) }}</dd></div>
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Status</dt><dd><x-status-badge :tone="$deposit->status->tone()">{{ $deposit->status->label() }}</x-status-badge></dd></div>
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Diterima</dt><dd>{{ $deposit->received_at?->translatedFormat('j F Y') ?? 'Belum diterima' }}</dd></div>
                @if (! $isActive)
                    <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Potongan</dt><dd class="angka">{{ App\Support\Rupiah::format($deposit->deduction_amount) }}</dd></div>
                    <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Dikembalikan</dt><dd class="angka">{{ App\Support\Rupiah::format($deposit->refund_amount) }}</dd></div>
                    @if ($deposit->deduction_note)
                        <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Alasan potongan</dt><dd>{{ $deposit->deduction_note }}</dd></div>
                    @endif
                @endif
            </dl>
            <p class="mt-space-sm text-body-sm text-secondary">Kostra hanya mencatat deposit; uangnya tidak ditahan atau disalurkan oleh Kostra.</p>
        </x-panel>
    @endif

    @if ($isActive)
        @can('operate', $lease)
            <x-panel title="Check-out">
                <form method="POST" action="{{ route('owner.leases.check-out', $lease) }}" class="flex flex-col gap-space-md">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                        <x-input name="checkout_date" type="date" label="Tanggal check-out" :value="$today->toDateString()" max="{{ $today->toDateString() }}" required />
                        <x-select name="ended_reason" label="Alasan">
                            @foreach (App\Enums\LeaseEndReason::checkOutValues() as $value)
                                <option value="{{ $value }}" @selected(old('ended_reason') === $value)>{{ App\Enums\LeaseEndReason::from($value)->label() }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <x-input name="checkout_notes" label="Catatan kondisi kamar saat keluar" />

                    @if ($outstanding > 0)
                        <p class="rounded-lg bg-tertiary-fixed text-on-tertiary-fixed px-3 py-2 text-body-sm">
                            Sisa tagihan belum dibayar: <span class="angka font-label-lg">{{ App\Support\Rupiah::format($outstanding) }}</span>.
                        </p>
                    @endif

                    @can('decideMoney', $lease)
                        @if ($deposit && $deposit->status->value === 'held')
                            <fieldset class="flex flex-col gap-space-md">
                                <legend class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-1">Potongan deposit</legend>
                                <p class="text-body-sm text-secondary">Deposit tercatat <span class="angka">{{ App\Support\Rupiah::format($deposit->amount) }}</span>. Pengembalian = deposit dikurangi potongan.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                                    <x-input name="deduction_amount" inputmode="numeric" label="Potongan (Rp)" hint="Kosongkan bila tidak ada potongan." />
                                    <x-input name="deduction_note" label="Alasan potongan" hint="Wajib bila ada potongan." />
                                </div>
                            </fieldset>
                        @endif
                    @endcan

                    <div><x-button type="submit" variant="danger">Check-out penghuni</x-button></div>
                </form>
            </x-panel>

            @if ($lease->end_date)
                <x-panel title="Perpanjang kontrak">
                    <form method="POST" action="{{ route('owner.leases.renew', $lease) }}" class="flex flex-col gap-space-md">
                        @csrf
                        <p class="text-body-sm text-secondary">Kontrak baru mulai {{ $lease->end_date->addDay()->translatedFormat('j F Y') }}, dengan harga dan penghuni yang sama. Deposit ikut dipindahkan.</p>
                        <x-input name="end_date" type="date" label="Tanggal akhir kontrak baru" required />
                        <x-input name="notes" label="Catatan" />
                        <div><x-button type="submit" variant="secondary">Perpanjang</x-button></div>
                    </form>
                </x-panel>
            @endif

            <x-panel title="Pindah kamar">
                <form method="POST" action="{{ route('owner.leases.transfer', $lease) }}" class="flex flex-col gap-space-md">
                    @csrf
                    <x-select name="to_room_id" label="Kamar tujuan (hanya kamar kosong)" placeholder="Pilih kamar" required>
                        @foreach ($vacantRooms as $room)
                            <option value="{{ $room->id }}" @selected((int) old('to_room_id') === $room->id)>{{ $room->property->name }} · Kamar {{ $room->number }} · {{ App\Support\Rupiah::format($room->price) }}</option>
                        @endforeach
                    </x-select>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                        <x-input name="transferred_on" type="date" label="Tanggal pindah" :value="$today->toDateString()" max="{{ $today->toDateString() }}" required />
                        @can('decideMoney', $lease)
                            <x-input name="new_rent_amount" inputmode="numeric" label="Harga sewa baru (Rp)" hint="Kosongkan bila harga tidak berubah. Berlaku untuk tagihan berikutnya." />
                        @endcan
                    </div>
                    <x-input name="reason" label="Alasan pindah" />
                    <div><x-button type="submit" variant="secondary">Pindahkan</x-button></div>
                </form>
            </x-panel>
        @endcan
    @endif

    @if ($lease->transfers->isNotEmpty())
        <x-panel title="Riwayat pindah kamar">
            @foreach ($lease->transfers as $transfer)
                <div class="py-space-sm border-b border-outline-variant/40 last:border-0 text-body-md">
                    <span class="angka">{{ $transfer->transferred_on->translatedFormat('j F Y') }}</span>:
                    kamar <span class="angka">{{ $transfer->fromRoom?->number }}</span> → <span class="angka">{{ $transfer->toRoom?->number }}</span>
                    @if ($transfer->new_rent_amount)
                        · harga baru <span class="angka">{{ App\Support\Rupiah::format($transfer->new_rent_amount) }}</span>
                    @endif
                    @if ($transfer->reason)<span class="text-secondary"> · {{ $transfer->reason }}</span>@endif
                    <span class="text-secondary text-body-sm"> · oleh {{ $transfer->creator?->name ?? '—' }}</span>
                </div>
            @endforeach
        </x-panel>
    @endif

    <x-panel title="Foto kondisi kamar">
        @forelse ($lease->attachments as $attachment)
            <div class="py-space-sm border-b border-outline-variant/40 last:border-0 flex items-center justify-between gap-3">
                <div class="font-body-md">Foto {{ $attachment->created_at->timezone($tz)->translatedFormat('j F Y H:i') }}</div>
                <div class="flex gap-2">
                    <x-button :href="$attachment->temporaryUrl()" variant="secondary" target="_blank" rel="noopener">Lihat</x-button>
                    @can('delete', $attachment)
                        <form method="POST" action="{{ route('owner.attachments.destroy', $attachment) }}" onsubmit="return confirm('Hapus foto ini?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger">Hapus</x-button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-on-surface-variant">Belum ada foto.</p>
        @endforelse

        @can('operate', $lease)
            <form method="POST" action="{{ route('owner.leases.attachments.store', $lease) }}" enctype="multipart/form-data" class="mt-space-md flex flex-col gap-space-md border-t border-outline-variant/40 pt-space-md">
                @csrf
                <x-input name="file" type="file" accept=".jpg,.jpeg,.png,.pdf" label="Unggah foto (JPG, PNG, atau PDF, maksimal 5 MB)" required />
                <div><x-button type="submit" variant="secondary">Unggah</x-button></div>
            </form>
        @endcan
    </x-panel>
@endsection
