@extends('layouts.owner')

@section('title', 'Kontrak')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-space-sm">
        <nav class="flex flex-wrap gap-2" aria-label="Filter kontrak">
            @foreach ([
                'active' => 'Aktif',
                'expiring' => 'Segera berakhir (≤ '.App\Models\Lease::EXPIRING_SOON_DAYS.' hari)',
                'ended' => 'Riwayat',
                'all' => 'Semua',
            ] as $key => $label)
                <a href="{{ route('owner.leases.index', array_filter(['tab' => $key, 'q' => $q, 'property_id' => $propertyId])) }}"
                   @if ($tab === $key) aria-current="page" @endif
                   class="px-3 py-1.5 rounded-full font-label-md {{ $tab === $key ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                    {{ $label }} <span class="angka">({{ $counts[$key] }})</span>
                </a>
            @endforeach
        </nav>
        @can('create', App\Models\Lease::class)
            <x-button :href="route('owner.leases.create')">Check-in penghuni</x-button>
        @endcan
    </div>

    <form method="GET" action="{{ route('owner.leases.index') }}" class="panel p-space-md grid grid-cols-1 sm:grid-cols-3 gap-space-md items-end">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-input name="q" label="Cari nomor kamar atau nama penghuni" :value="$q" />
        <x-select name="property_id" label="Properti" placeholder="Semua properti">
            @foreach ($properties as $property)
                <option value="{{ $property->id }}" @selected($propertyId == $property->id)>{{ $property->name }}</option>
            @endforeach
        </x-select>
        <div class="flex gap-2">
            <x-button type="submit">Terapkan</x-button>
            <x-button :href="route('owner.leases.index', ['tab' => $tab])" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    <x-panel>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kamar</th>
                        <th>Penghuni</th>
                        <th>Masa kontrak</th>
                        <th>Sewa</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leases as $lease)
                        @php($days = $lease->daysRemaining($today))
                        <tr>
                            <td>
                                <a href="{{ route('owner.leases.show', $lease) }}" class="font-label-lg text-primary hover:underline angka">{{ $lease->room->number }}</a>
                                <div class="font-body-sm text-secondary">{{ $lease->property->name }}</div>
                            </td>
                            <td>
                                {{ $lease->primaryResident->name }}
                                @if ($lease->residents_count > 1)
                                    <span class="font-body-sm text-secondary">+{{ $lease->residents_count - 1 }} lainnya</span>
                                @endif
                            </td>
                            <td class="angka">
                                {{ $lease->start_date->translatedFormat('j M Y') }} s/d {{ $lease->end_date?->translatedFormat('j M Y') ?? '—' }}
                                @if ($lease->status->value === 'active' && $days !== null)
                                    <div class="font-body-sm {{ $days < 0 ? 'text-error' : 'text-secondary' }}">
                                        {{ $days < 0 ? 'Lewat '.abs($days).' hari' : ($days === 0 ? 'Berakhir hari ini' : $days.' hari lagi') }}
                                    </div>
                                @endif
                            </td>
                            <td class="angka">{{ App\Support\Rupiah::format($lease->rent_amount) }}</td>
                            <td>
                                <x-status-badge :tone="$lease->status->tone()">{{ $lease->status->label() }}</x-status-badge>
                                @if ($lease->isPastEnd($today))
                                    <x-status-badge tone="danger">Lewat masa sewa</x-status-badge>
                                @elseif ($lease->status->value === 'active' && $days !== null && $days <= App\Models\Lease::EXPIRING_SOON_DAYS)
                                    <x-status-badge tone="warn">Segera berakhir</x-status-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-on-surface-variant">Tidak ada kontrak yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{ $leases->links() }}
@endsection
