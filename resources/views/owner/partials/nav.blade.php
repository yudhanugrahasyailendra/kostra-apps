{{-- Menu utama panel pemilik. Gaya dan label mengikuti sidebar docs/screens; penghitung dari OwnerLayoutComposer. --}}
@php
    $items = [
        ['owner.dashboard', ['owner.dashboard'], 'dashboard', 'Ringkasan', null, null],
        ['owner.rooms.index', ['owner.rooms.*', 'owner.properties.*', 'owner.room-types.*'], 'meeting_room', 'Properti & Kamar', null, null],
        ['owner.residents.index', ['owner.residents.*', 'owner.leases.*'], 'group', 'Penghuni & Kontrak', null, null],
        ['owner.invoices.index', ['owner.invoices.*', 'owner.payments.*'], 'receipt_long', 'Tagihan & Invoice', null, ($shell['overdueInvoices'] ?? 0) > 0 ? ['danger', (string) $shell['overdueInvoices']] : null],
        ['owner.proofs.index', ['owner.proofs.*'], 'verified', 'Verifikasi Pembayaran', null, ($shell['pendingProofs'] ?? 0) > 0 ? ['warn', (string) $shell['pendingProofs']] : null],
        ['owner.complaints.index', ['owner.complaints.*'], 'home_repair_service', 'Komplain & Perawatan', null, ($shell['openComplaints'] ?? 0) > 0 ? ['warn', (string) $shell['openComplaints']] : null],
        ['owner.messages.index', ['owner.messages.*', 'owner.message-templates.*'], 'chat', 'Kuota WhatsApp', 'settings.manage', null],
        ['owner.reports.index', ['owner.reports.*', 'owner.expenses.*'], 'bar_chart', 'Laporan Keuangan', 'finance.view', null],
        ['owner.settings', ['owner.settings', 'owner.staff.*', 'owner.subscription.*'], 'settings', 'Pengaturan Kos', 'settings.manage', null],
    ];
@endphp

@foreach ($items as [$route, $patterns, $icon, $label, $ability, $badge])
    @continue($ability && ! auth()->user()->can($ability))
    @php($isActive = request()->routeIs(...$patterns))
    <a href="{{ route($route) }}" @if ($isActive) aria-current="page" @endif
       class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl transition-all {{ $isActive ? 'bg-primary text-on-primary font-label-lg shadow-[0_4px_12px_rgba(13,122,95,0.12)]' : 'font-body-md text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">
        <span class="flex items-center gap-3 min-w-0">
            <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ $icon }}</span>
            <span class="truncate">{{ $label }}</span>
        </span>
        @if ($badge)
            <span class="badge badge-{{ $badge[0] }} px-2 py-0.5 shrink-0 angka">{{ $badge[1] }}</span>
        @endif
    </a>
@endforeach
