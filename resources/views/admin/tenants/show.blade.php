@extends('layouts.owner')

@section('title', $tenant->name)

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('content')
    @php($limit = fn ($value) => $value === null ? 'tanpa batas' : number_format($value, 0, ',', '.'))
    @php($statusTone = match ($tenant->status) { App\Enums\TenantStatus::Active => 'ok', App\Enums\TenantStatus::Trial => 'warn', App\Enums\TenantStatus::Suspended => 'danger', default => 'neutral' })

    <a href="{{ route('admin.tenants.index') }}" class="text-primary">&larr; Semua tenant</a>

    <x-panel>
        <div class="flex flex-wrap items-start justify-between gap-space-md">
            <div>
                <h2 class="font-headline-lg text-headline-lg">{{ $tenant->name }}</h2>
                <div class="mt-1 flex flex-wrap gap-2">
                    <x-status-badge :tone="$statusTone">{{ $tenant->status->label() }}</x-status-badge>
                    <x-status-badge tone="neutral">{{ $tenant->plan->name }}</x-status-badge>
                    @if ($subscription)
                        <x-status-badge :tone="$subscription->status->tone()">{{ $subscription->status->label() }}</x-status-badge>
                    @endif
                </div>
                <div class="font-body-sm text-secondary mt-1">{{ $tenant->slug }} · {{ $tenant->timezone }}</div>
            </div>
            <div class="flex gap-2">
                @if ($tenant->status === App\Enums\TenantStatus::Suspended)
                    <form method="POST" action="{{ route('admin.tenants.activate', $tenant) }}">@csrf<x-button type="submit">Aktifkan kembali</x-button></form>
                @elseif ($tenant->status !== App\Enums\TenantStatus::Closed)
                    <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" onsubmit="return confirm('Tangguhkan tenant ini? Pemilik hanya bisa melihat dan mengekspor data.')">@csrf<x-button type="submit" variant="danger">Tangguhkan</x-button></form>
                @endif
            </div>
        </div>

        <dl class="mt-space-md grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-space-sm">
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Pemilik</dt><dd>{{ $owner?->name ?? '—' }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Kontak pemilik</dt><dd class="angka">{{ $owner?->email ?? $owner?->phone ?? '—' }}</dd></div>
            @if ($tenant->suspended_at)
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Ditangguhkan sejak</dt><dd class="angka">{{ $tenant->suspended_at->timezone($tenant->timezone)->translatedFormat('j F Y') }}</dd></div>
            @endif
            @if ($subscription)
                <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Periode berjalan</dt>
                    <dd class="angka">{{ $subscription->current_period_start->translatedFormat('j M Y') }} – {{ $subscription->current_period_end?->translatedFormat('j M Y') ?? 'tanpa batas' }}</dd></div>
                @if ($subscription->grace_ends_at)
                    <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Masa tenggang sampai</dt><dd class="angka">{{ $subscription->grace_ends_at->timezone($tenant->timezone)->subSecond()->translatedFormat('j F Y') }}</dd></div>
                @endif
            @endif
        </dl>

        <dl class="mt-space-md grid grid-cols-2 lg:grid-cols-4 gap-space-md">
            <div class="rounded-xl bg-surface-container-low p-space-md"><dt class="font-label-sm text-secondary uppercase tracking-wider">Properti</dt><dd class="angka font-label-lg">{{ $usage['properties'] }} / {{ $limit($tenant->plan->max_properties) }}</dd></div>
            <div class="rounded-xl bg-surface-container-low p-space-md"><dt class="font-label-sm text-secondary uppercase tracking-wider">Kamar</dt><dd class="angka font-label-lg">{{ $usage['rooms'] }} / {{ $limit($tenant->plan->max_rooms) }}</dd></div>
            <div class="rounded-xl bg-surface-container-low p-space-md"><dt class="font-label-sm text-secondary uppercase tracking-wider">Pengguna</dt><dd class="angka font-label-lg">{{ $usage['users'] }} / {{ $limit($tenant->plan->max_users) }}</dd></div>
            <div class="rounded-xl bg-surface-container-low p-space-md"><dt class="font-label-sm text-secondary uppercase tracking-wider">Pesan bulan ini</dt><dd class="angka font-label-lg">{{ $usage['messages'] }} / {{ $limit($messageLimit) }}</dd></div>
        </dl>
    </x-panel>

    <x-panel title="Tagihan langganan">
        @forelse ($invoices as $invoice)
            <div class="flex flex-wrap items-center justify-between gap-space-md py-space-sm border-t border-outline-variant/40 first:border-0">
                <div>
                    <div class="font-label-lg angka">{{ $invoice->number }}</div>
                    <div class="font-body-sm text-secondary angka">{{ $invoice->period_start->translatedFormat('j M Y') }} – {{ $invoice->period_end->translatedFormat('j M Y') }} · {{ App\Support\Rupiah::format($invoice->amount) }} · jatuh tempo {{ $invoice->due_date->translatedFormat('j M Y') }}</div>
                    @if ($invoice->paid_at)
                        <div class="font-body-sm text-secondary">Dibayar {{ $invoice->paid_at->timezone($tenant->timezone)->translatedFormat('j M Y') }} · {{ $invoice->payment_method }} {{ $invoice->reference }}</div>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge>
                </div>
                @if ($invoice->isPending())
                    <div class="w-full flex flex-wrap items-end gap-space-sm">
                        <form method="POST" action="{{ route('admin.subscription-invoices.pay', $invoice) }}" class="flex flex-wrap items-end gap-space-sm">
                            @csrf
                            <x-input name="payment_method" label="Metode" value="Transfer bank" required />
                            <x-input name="reference" label="Referensi (opsional)" />
                            <x-input name="paid_on" type="date" label="Tanggal transfer" />
                            <x-button type="submit">Catat pembayaran</x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.subscription-invoices.void', $invoice) }}" onsubmit="return confirm('Batalkan tagihan ini?')">
                            @csrf
                            <x-button type="submit" variant="secondary">Batalkan tagihan</x-button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-on-surface-variant">Belum ada tagihan langganan.</p>
        @endforelse
    </x-panel>

    <x-panel title="Tetapkan paket">
        <p class="font-body-sm text-secondary mb-space-md">Mengubah paket langsung tanpa tagihan (promosi, koreksi, atau kesepakatan di luar sistem). Tagihan yang menunggu untuk paket sebelumnya dibatalkan.</p>
        <form method="POST" action="{{ route('admin.tenants.plan', $tenant) }}" class="flex flex-wrap items-end gap-space-md">
            @csrf
            <x-select name="plan_id" label="Paket" required>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" @selected($plan->id === $tenant->plan_id)>{{ $plan->name }}{{ $plan->is_active ? '' : ' (nonaktif)' }}</option>
                @endforeach
            </x-select>
            <x-select name="billing_period" label="Periode">
                <option value="monthly">Bulanan</option>
                <option value="yearly">Tahunan</option>
            </x-select>
            <label class="flex items-center gap-2 font-label-md h-12 lg:h-10">
                <input type="checkbox" name="ignore_limits" value="1" class="rounded border-outline-variant text-primary focus:ring-primary/20">
                Lewati pemeriksaan batas
            </label>
            <x-button type="submit">Tetapkan</x-button>
        </form>
        @error('plan')
            <p class="mt-space-sm font-body-sm text-error" role="alert">{{ $message }}</p>
        @enderror
    </x-panel>

    <x-panel title="Riwayat langganan">
        @foreach ($history as $item)
            <div class="flex flex-wrap items-center justify-between gap-space-sm py-space-sm border-t border-outline-variant/40 first:border-0">
                <span class="font-label-lg">{{ $item->plan->name }}</span>
                <span class="font-body-sm text-secondary angka">{{ $item->current_period_start->translatedFormat('j M Y') }} – {{ $item->current_period_end?->translatedFormat('j M Y') ?? 'tanpa batas' }}</span>
                <x-status-badge :tone="$item->status->tone()">{{ $item->status->label() }}</x-status-badge>
            </div>
        @endforeach
    </x-panel>
@endsection
