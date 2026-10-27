@extends('layouts.owner')

@section('title', 'Pengaturan')

@section('content')
    <x-page-header eyebrow="Konfigurasi sistem" title="Pengaturan Kos & Operasional" subtitle="Atur penagihan, rekening pembayaran, tim staf, dan data kos Anda." />

    @php
        $cards = [
            ['owner.settings.billing', 'receipt_long', 'Aturan Penagihan', 'Tanggal terbit, jatuh tempo, pro rata, dan jadwal pengingat tagihan.', null],
            ['owner.payment-accounts.index', 'account_balance', 'Rekening & Metode Bayar', 'Rekening bank dan QRIS yang tampil di portal penghuni. Pembayaran dicatat setelah terjadi di luar sistem.', null],
            ['owner.staff.index', 'group', 'Kelola Staf & Hak Akses', 'Undang staf lewat email atau WhatsApp dan atur izin tiap orang.', 'viewAny:'.App\Models\User::class],
            ['owner.export.index', 'download', 'Ekspor Seluruh Data', 'Unduh data kos (properti, kamar, penghuni, tagihan, pembayaran) sebagai Excel.', 'export-data'],
            ['owner.subscription.show', 'workspace_premium', 'Paket Langganan & Kuota', 'Lihat paket, pemakaian kamar, dan ajukan perubahan paket.', 'subscription.manage'],
        ];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-space-md">
        @foreach ($cards as [$route, $icon, $title, $text, $ability])
            @continue($ability && ! (str_starts_with($ability, 'viewAny:') ? auth()->user()->can('viewAny', substr($ability, 8)) : auth()->user()->can($ability)))
            <a href="{{ route($route) }}" class="group p-space-md rounded-xl bg-surface-container-lowest shadow-sm hover:shadow-md transition-shadow flex items-start gap-space-md">
                <span class="kpi-icon bg-primary-fixed text-on-primary-fixed-variant" aria-hidden="true"><span class="material-symbols-outlined">{{ $icon }}</span></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-headline-md text-headline-md group-hover:text-primary transition-colors">{{ $title }}</span>
                    <span class="block font-body-sm text-body-sm text-secondary">{{ $text }}</span>
                </span>
                <span class="material-symbols-outlined text-secondary" aria-hidden="true">chevron_right</span>
            </a>
        @endforeach
    </div>
@endsection
