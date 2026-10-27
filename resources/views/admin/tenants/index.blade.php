@extends('layouts.owner')

@section('title', 'Tenant')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('content')
    <form method="GET" action="{{ route('admin.tenants.index') }}" class="panel p-space-md grid grid-cols-2 lg:grid-cols-4 gap-space-md items-end">
        <div class="col-span-2">
            <x-input name="q" label="Cari nama kos, identitas, atau pemilik (nama, email, nomor)" :value="$q" />
        </div>
        <x-select name="status" label="Status" placeholder="Semua status">
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select>
        <x-select name="plan_id" label="Paket" placeholder="Semua paket">
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}" @selected((int) $planId === $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </x-select>
        <div class="flex gap-2 col-span-2 lg:col-span-4">
            <x-button type="submit">Cari</x-button>
            <x-button :href="route('admin.tenants.index')" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    <x-panel>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Paket</th>
                        <th>Status</th>
                        <th class="text-right">Properti</th>
                        <th class="text-right">Kamar</th>
                        <th class="text-right">Pesan bulan ini</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        @php($subscription = $subscriptions[$tenant->id] ?? null)
                        <tr>
                            <td>
                                <a href="{{ route('admin.tenants.show', $tenant) }}" class="font-label-lg text-primary hover:underline">{{ $tenant->name }}</a>
                                <div class="font-body-sm text-secondary">{{ $tenant->slug }}</div>
                            </td>
                            <td>
                                {{ $tenant->plan->name }}
                                @if ($subscription)
                                    <div><x-status-badge :tone="$subscription->status->tone()">{{ $subscription->status->label() }}</x-status-badge></div>
                                @endif
                            </td>
                            <td><x-status-badge :tone="match ($tenant->status) { App\Enums\TenantStatus::Active => 'ok', App\Enums\TenantStatus::Trial => 'warn', App\Enums\TenantStatus::Suspended => 'danger', default => 'neutral' }">{{ $tenant->status->label() }}</x-status-badge></td>
                            <td class="angka text-right">{{ $properties[$tenant->id] ?? 0 }}</td>
                            <td class="angka text-right">{{ $rooms[$tenant->id] ?? 0 }}</td>
                            <td class="angka text-right">{{ $messages[$tenant->id] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4 text-on-surface-variant">Tidak ada tenant yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{ $tenants->links() }}
@endsection
