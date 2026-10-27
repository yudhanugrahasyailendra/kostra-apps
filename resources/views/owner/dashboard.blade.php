@extends('layouts.owner')

@section('title', 'Ringkasan')

@section('content')
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Okupansi</div>
            <div class="font-currency-stat text-currency-stat angka" data-stat="occupancy">{{ App\Services\Reports\ReportTable::displayAs('percent', $occupancy['percent']) }}</div>
            <div class="font-body-sm text-secondary">{{ $occupancy['occupied'] }} dari {{ $occupancy['total'] }} kamar terisi</div>
        </div>

        @if ($finance)
            <div class="panel p-space-md">
                <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Pemasukan {{ $finance['monthLabel'] }}</div>
                <div class="font-currency-stat text-currency-stat angka" data-stat="income">{{ App\Support\Rupiah::format($finance['income']) }}</div>
                <a class="font-body-sm text-primary hover:underline" href="{{ route('owner.reports.show', 'pemasukan') }}">Lihat laporan</a>
            </div>
            <div class="panel p-space-md">
                <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Total tunggakan</div>
                <div class="font-currency-stat text-currency-stat angka" data-stat="arrears">{{ App\Support\Rupiah::format($finance['arrears']) }}</div>
                <a class="font-body-sm text-primary hover:underline" href="{{ route('owner.reports.show', 'tunggakan') }}">Lihat laporan</a>
            </div>
        @endif

        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Kontrak segera habis</div>
            <div class="font-currency-stat text-currency-stat angka" data-stat="expiring">{{ $expiringCount }}</div>
            <div class="font-body-sm text-secondary">dalam {{ $expiringDays }} hari ke depan</div>
        </div>
    </div>

    @if ($finance)
        @php($maxBar = max(1, collect($finance['chart'])->flatMap(fn ($m) => [$m['income'], $m['expense']])->max()))
        <x-panel title="Pemasukan dan pengeluaran 6 bulan terakhir">
            <div class="flex items-center gap-space-md font-label-md mb-space-sm">
                <span class="flex items-center gap-2"><span class="inline-block w-3 h-3 rounded-sm bg-primary" aria-hidden="true"></span> Pemasukan</span>
                <span class="flex items-center gap-2"><span class="inline-block w-3 h-3 rounded-sm bg-tertiary" aria-hidden="true"></span> Pengeluaran</span>
            </div>
            <div class="flex items-end gap-space-sm h-48" role="img" aria-label="Grafik batang pemasukan dan pengeluaran per bulan; datanya ada di tabel di bawah">
                @foreach ($finance['chart'] as $month)
                    <div class="group relative flex-1 flex items-end justify-center gap-1 h-full" tabindex="0">
                        <div class="w-1/3 bg-primary rounded-t" style="height: {{ round($month['income'] / $maxBar * 100) }}%"></div>
                        <div class="w-1/3 bg-tertiary rounded-t" style="height: {{ round($month['expense'] / $maxBar * 100) }}%"></div>
                        <div class="pointer-events-none absolute bottom-full mb-1 hidden group-hover:block group-focus:block z-10 rounded-lg bg-secondary text-on-secondary px-2 py-1 font-label-sm whitespace-nowrap" data-tip>
                            {{ Carbon\CarbonImmutable::parse($month['month'].'-01')->locale('id')->translatedFormat('F Y') }}:
                            masuk {{ App\Support\Rupiah::format($month['income']) }}, keluar {{ App\Support\Rupiah::format($month['expense']) }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-space-sm font-label-sm text-secondary mt-1" aria-hidden="true">
                @foreach ($finance['chart'] as $month)
                    <div class="flex-1 text-center">{{ Carbon\CarbonImmutable::parse($month['month'].'-01')->locale('id')->translatedFormat('M') }}</div>
                @endforeach
            </div>

            <details class="mt-space-md">
                <summary class="cursor-pointer text-primary font-label-lg">Lihat sebagai tabel</summary>
                <div class="overflow-x-auto mt-space-sm">
                    <table class="w-full text-left">
                        <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                            <tr><th class="p-3">Bulan</th><th class="p-3 text-right">Pemasukan</th><th class="p-3 text-right">Pengeluaran</th><th class="p-3 text-right">Saldo bersih</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($finance['chart'] as $month)
                                <tr class="border-t border-outline-variant/40">
                                    <td class="p-3">{{ Carbon\CarbonImmutable::parse($month['month'].'-01')->locale('id')->translatedFormat('F Y') }}</td>
                                    <td class="p-3 angka text-right">{{ App\Support\Rupiah::format($month['income']) }}</td>
                                    <td class="p-3 angka text-right">{{ App\Support\Rupiah::format($month['expense']) }}</td>
                                    <td class="p-3 angka text-right">{{ $month['net'] < 0 ? '−' : '' }}{{ App\Support\Rupiah::format(abs($month['net'])) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </x-panel>
    @endif

    @if ($expiring->isNotEmpty())
        <x-panel title="Kontrak segera habis">
            @foreach ($expiring as $lease)
                <a href="{{ route('owner.leases.show', $lease) }}" class="flex items-center justify-between py-space-sm border-b border-outline-variant/40 last:border-0 hover:bg-surface-container-low/50">
                    <span class="font-label-lg">Kamar {{ $lease->room?->number }} · {{ $lease->primaryResident?->name }}</span>
                    <span class="font-body-sm text-secondary angka">berakhir {{ $lease->end_date->translatedFormat('j M Y') }}</span>
                </a>
            @endforeach
        </x-panel>
    @endif

    @forelse ($floorPlan as $propertyName => $floors)
        <x-panel :title="'Denah '.$propertyName">
            <div class="flex flex-col gap-space-md">
                @foreach ($floors as $floor => $rooms)
                    <div>
                        <h3 class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-space-sm">
                            {{ $floor === 0 ? 'Tanpa lantai' : 'Lantai '.$floor }}
                        </h3>
                        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-space-sm">
                            @foreach ($rooms as $room)
                                <x-room-plate
                                    :number="$room->number"
                                    :label="$room->status->label()"
                                    :tone="$room->status->tone()"
                                    :sub="$room->activeLease?->primaryResident?->name ?? $room->roomType?->name"
                                    :href="route('owner.rooms.index', ['property_id' => $room->property_id])" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    @empty
        <x-panel title="Selamat datang, {{ auth()->user()->name }}">
            <p class="text-on-surface-variant mb-space-md">Kos Anda masih kosong. Mulai dengan menambahkan properti dan kamar.</p>
            @can('create', App\Models\Property::class)
                <x-button :href="route('owner.properties.index')">Tambah properti</x-button>
            @endcan
        </x-panel>
    @endforelse
@endsection
