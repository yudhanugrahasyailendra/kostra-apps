@extends('layouts.owner')

@section('title', 'Paket')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('content')
    @php($limit = fn ($value) => $value === null ? 'tanpa batas' : number_format($value, 0, ',', '.'))

    <x-panel title="Paket langganan">
        <p class="font-body-sm text-secondary mb-space-md">Harga baru hanya berlaku untuk tagihan langganan berikutnya. Batas dan kuota berlaku seketika bagi semua tenant di paket itu.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Paket</th><th class="p-3 text-right">Harga/bulan</th><th class="p-3 text-right">Properti</th><th class="p-3 text-right">Kamar</th>
                        <th class="p-3 text-right">Pengguna</th><th class="p-3 text-right">Pesan</th><th class="p-3 text-right">Tenant</th><th class="p-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($plans as $plan)
                        <tr class="border-t border-outline-variant/40">
                            <td class="p-3 font-label-lg">{{ $plan->name }} @unless ($plan->is_active)<x-status-badge tone="neutral">Nonaktif</x-status-badge>@endunless</td>
                            <td class="p-3 angka text-right">{{ App\Support\Rupiah::format($plan->price_monthly) }}</td>
                            <td class="p-3 angka text-right">{{ $limit($plan->max_properties) }}</td>
                            <td class="p-3 angka text-right">{{ $limit($plan->max_rooms) }}</td>
                            <td class="p-3 angka text-right">{{ $limit($plan->max_users) }}</td>
                            <td class="p-3 angka text-right">{{ number_format($plan->monthly_message_quota, 0, ',', '.') }}</td>
                            <td class="p-3 angka text-right">{{ $plan->tenants_count }}</td>
                            <td class="p-3 text-right"><a class="text-primary hover:underline" href="{{ route('admin.plans.edit', $plan) }}">Ubah</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-panel>
@endsection
