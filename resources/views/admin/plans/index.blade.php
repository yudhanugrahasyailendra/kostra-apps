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
            <table class="table">
                <thead>
                    <tr>
                        <th>Paket</th><th class="text-right">Harga/bulan</th><th class="text-right">Properti</th><th class="text-right">Kamar</th>
                        <th class="text-right">Pengguna</th><th class="text-right">Pesan</th><th class="text-right">Tenant</th><th><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($plans as $plan)
                        <tr>
                            <td class="font-label-lg">{{ $plan->name }} @unless ($plan->is_active)<x-status-badge tone="neutral">Nonaktif</x-status-badge>@endunless</td>
                            <td class="angka text-right">{{ App\Support\Rupiah::format($plan->price_monthly) }}</td>
                            <td class="angka text-right">{{ $limit($plan->max_properties) }}</td>
                            <td class="angka text-right">{{ $limit($plan->max_rooms) }}</td>
                            <td class="angka text-right">{{ $limit($plan->max_users) }}</td>
                            <td class="angka text-right">{{ number_format($plan->monthly_message_quota, 0, ',', '.') }}</td>
                            <td class="angka text-right">{{ $plan->tenants_count }}</td>
                            <td class="text-right"><a class="text-primary hover:underline" href="{{ route('admin.plans.edit', $plan) }}">Ubah</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-panel>
@endsection
