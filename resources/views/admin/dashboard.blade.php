@extends('layouts.owner')

@section('title', 'Panel Super Admin')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('content')
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Tenant aktif</div>
            <div class="font-currency-stat text-currency-stat angka" data-stat="active">{{ $tenants['active'] }}</div>
            <div class="font-body-sm text-secondary">{{ $tenants['trial'] }} percobaan · {{ $tenants['suspended'] }} ditangguhkan · {{ $tenants['closed'] }} ditutup</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Berlangganan berbayar</div>
            <div class="font-currency-stat text-currency-stat angka" data-stat="paying">{{ $paying }}</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Masa percobaan</div>
            <div class="font-currency-stat text-currency-stat angka" data-stat="trialing">{{ $trialing }}</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Menunggu pembayaran</div>
            <div class="font-currency-stat text-currency-stat angka" data-stat="pending">{{ $pendingCount }}</div>
            <div class="font-body-sm text-secondary angka">{{ App\Support\Rupiah::format($pendingAmount) }}</div>
        </div>
    </div>

    <x-panel title="Tagihan langganan menunggu pembayaran">
        @forelse ($recentPending as $invoice)
            <a href="{{ route('admin.tenants.show', $invoice->tenant_id) }}" class="flex items-center justify-between gap-space-md py-space-sm border-t border-outline-variant/40 first:border-0 hover:bg-surface-container-low/50">
                <span class="font-label-lg angka">{{ $invoice->number }} <span class="font-body-sm text-secondary">· {{ $invoice->subscription?->plan?->name }}</span></span>
                <span class="angka">{{ App\Support\Rupiah::format($invoice->amount) }} · jatuh tempo {{ $invoice->due_date->translatedFormat('j M Y') }}</span>
            </a>
        @empty
            <p class="text-on-surface-variant">Tidak ada tagihan yang menunggu.</p>
        @endforelse
        @if ($pastDue > 0)
            <p class="mt-space-sm font-body-sm text-secondary">{{ $pastDue }} langganan dalam masa tenggang.</p>
        @endif
    </x-panel>
@endsection
