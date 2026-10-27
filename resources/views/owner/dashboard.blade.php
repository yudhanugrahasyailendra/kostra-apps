@extends('layouts.owner')

@section('title', 'Dashboard')

@section('content')
    {{-- Sapaan dan aksi cepat (docs/screens/01-dashboard) --}}
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-space-md">
        <div>
            <h2 class="font-headline-lg-mobile text-headline-lg-mobile lg:font-headline-xl lg:text-headline-xl">{{ $greeting }}, {{ strtok(auth()->user()->name, ' ') }}!</h2>
            <p class="font-body-md text-body-md text-secondary">Rangkuman operasional kos Anda per hari ini, {{ $today->locale('id')->translatedFormat('j F Y') }}.</p>
        </div>
        <div class="flex flex-wrap items-center gap-space-sm">
            @can('create', App\Models\Resident::class)
                <a href="{{ route('owner.residents.create') }}" class="btn btn-primary"><span class="material-symbols-outlined text-base" aria-hidden="true">person_add</span> Tambah Penghuni</a>
            @endcan
        </div>
    </div>

    {{-- Kartu angka --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-sm">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-label">Okupansi kamar</div>
                    <div class="font-currency-stat text-currency-stat angka" data-stat="occupancy">{{ App\Services\Reports\ReportTable::displayAs('percent', $occupancy['percent']) }}</div>
                    <div class="font-label-md text-label-md text-primary angka">{{ $occupancy['occupied'] }} dari {{ $occupancy['total'] }} kamar terisi</div>
                </div>
                <span class="kpi-icon bg-primary-fixed text-on-primary-fixed-variant" aria-hidden="true"><span class="material-symbols-outlined">bed</span></span>
            </div>
            <div class="meter" role="img" aria-label="Okupansi {{ $occupancy['occupied'] }} dari {{ $occupancy['total'] }} kamar"><span style="width: {{ min(100, (float) $occupancy['percent']) }}%"></span></div>
            <div class="font-label-sm text-label-sm text-secondary angka">{{ max(0, $occupancy['total'] - $occupancy['occupied']) }} kamar kosong</div>
        </div>

        @if ($finance)
            <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="kpi-label">Pemasukan {{ $finance['monthLabel'] }}</div>
                        <div class="font-currency-stat text-currency-stat text-primary angka" data-stat="income">{{ App\Support\Rupiah::format($finance['income']) }}</div>
                    </div>
                    <span class="kpi-icon bg-secondary-container text-on-secondary-container" aria-hidden="true"><span class="material-symbols-outlined">trending_up</span></span>
                </div>
                <div class="font-label-sm text-label-sm text-secondary">
                    @if ($finance['incomeDelta'] !== null)
                        <span class="font-bold {{ $finance['incomeDelta'] >= 0 ? 'text-primary' : 'text-error' }} angka">{{ $finance['incomeDelta'] >= 0 ? '+' : '−' }}{{ number_format(abs($finance['incomeDelta']), 1, ',', '.') }}%</span> dibanding bulan lalu
                    @else
                        Belum ada pembanding bulan lalu
                    @endif
                </div>
                <a class="font-label-sm text-label-sm text-primary font-bold hover:underline" href="{{ route('owner.reports.show', 'pemasukan') }}">Lihat laporan</a>
            </div>

            <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="kpi-label">Tunggakan belum bayar</div>
                        <div class="font-currency-stat text-currency-stat text-error angka" data-stat="arrears">{{ App\Support\Rupiah::format($finance['arrears']) }}</div>
                    </div>
                    <span class="kpi-icon bg-error-container text-on-error-container" aria-hidden="true"><span class="material-symbols-outlined">event_busy</span></span>
                </div>
                <div class="flex items-center justify-between">
                    @if ($overdueCount > 0)
                        <x-status-badge tone="danger">{{ $overdueCount }} Tagihan Lewat Tempo</x-status-badge>
                    @else
                        <x-status-badge tone="ok">Tidak ada yang terlambat</x-status-badge>
                    @endif
                    <a class="font-label-sm text-label-sm text-primary font-bold hover:underline" href="{{ route('owner.reports.show', 'tunggakan') }}">Lihat laporan</a>
                </div>
            </div>
        @endif

        <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-sm">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-label">Kontrak segera habis</div>
                    <div class="font-currency-stat text-currency-stat angka" data-stat="expiring">{{ $expiringCount }}</div>
                </div>
                <span class="kpi-icon bg-tertiary-fixed text-on-tertiary-fixed" aria-hidden="true"><span class="material-symbols-outlined">event_upcoming</span></span>
            </div>
            <div class="font-label-sm text-label-sm text-secondary">dalam {{ $expiringDays }} hari ke depan</div>
        </div>
    </div>

    {{-- Bukti pembayaran menunggu verifikasi --}}
    @if ($pendingProofCount > 0)
        <section class="p-space-md rounded-xl bg-tertiary-fixed/40 flex flex-col gap-space-sm" data-stat="pending-proofs">
            <div class="flex items-start justify-between gap-space-sm">
                <div class="flex items-start gap-space-sm">
                    <span class="kpi-icon bg-tertiary text-on-tertiary" aria-hidden="true"><span class="material-symbols-outlined">mark_email_unread</span></span>
                    <div>
                        <h2 class="font-label-lg text-label-lg text-on-surface">{{ $pendingProofCount }} Bukti Pembayaran Menunggu Verifikasi</h2>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Penghuni mengunggah struk transfer lewat Portal Penghuni.</p>
                    </div>
                </div>
                <a href="{{ route('owner.proofs.index') }}" class="btn btn-primary btn-sm shrink-0">Tinjau</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
                @foreach ($pendingProofs as $proof)
                    <a href="{{ route('owner.proofs.index') }}" class="flex items-center gap-space-sm p-space-sm rounded-xl bg-surface-container-lowest shadow-sm hover:shadow-md transition-shadow">
                        <span class="kpi-icon bg-primary-fixed text-on-primary-fixed-variant" aria-hidden="true"><span class="material-symbols-outlined">receipt</span></span>
                        <span class="min-w-0">
                            <span class="block font-label-md text-label-md truncate">{{ $proof->resident?->name }}</span>
                            <span class="block font-label-md text-label-md text-primary angka">{{ App\Support\Rupiah::format($proof->amount) }}{{ $proof->invoice ? ' · '.$proof->invoice->number : '' }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-space-md items-start">
        <div class="xl:col-span-8 flex flex-col gap-space-md">
            @forelse ($floorPlan as $propertyName => $floors)
                <section class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col gap-space-md">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-space-sm">
                        <div>
                            <h2 class="font-headline-md text-headline-md">Denah {{ $propertyName }}</h2>
                            <p class="font-body-sm text-body-sm text-secondary">Ketersediaan unit per lantai.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-space-md font-label-sm text-label-sm">
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-primary" aria-hidden="true"></span> Terisi ({{ $floors->flatten()->where('status.value', 'occupied')->count() }})</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-outline-variant" aria-hidden="true"></span> Kosong ({{ $floors->flatten()->where('status.value', 'vacant')->count() }})</span>
                        </div>
                    </div>
                    @foreach ($floors as $floor => $rooms)
                        <div>
                            <h3 class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-sm">
                                {{ $floor === 0 ? 'Tanpa lantai' : 'Lantai '.$floor }} ({{ $rooms->count() }} kamar)
                            </h3>
                            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-space-sm">
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
                </section>
            @empty
                <x-panel title="Selamat datang, {{ auth()->user()->name }}">
                    <p class="text-on-surface-variant mb-space-md">Kos Anda masih kosong. Mulai dengan menambahkan properti dan kamar.</p>
                    @can('create', App\Models\Property::class)
                        <x-button :href="route('owner.properties.index')">Tambah properti</x-button>
                    @endcan
                </x-panel>
            @endforelse

            @if ($openInvoices->isNotEmpty())
                <section class="rounded-xl bg-surface-container-lowest shadow-sm overflow-hidden">
                    <div class="p-space-md flex items-start justify-between gap-space-sm">
                        <div>
                            <h2 class="font-headline-md text-headline-md">Tagihan &amp; Tunggakan Berjalan</h2>
                            <p class="font-body-sm text-body-sm text-secondary">Tagihan yang belum lunas, diurutkan dari jatuh tempo terdekat.</p>
                        </div>
                        <a href="{{ route('owner.invoices.index') }}" class="font-label-md text-label-md text-primary font-bold hover:underline inline-flex items-center gap-1 shrink-0">Lihat Semua Tagihan <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></a>
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Penghuni &amp; kamar</th><th>Jatuh tempo</th><th>Status</th><th class="text-right">Sisa tagihan</th></tr></thead>
                            <tbody>
                                @foreach ($openInvoices as $invoice)
                                    @php($late = $invoice->due_date->lt($today))
                                    <tr>
                                        <td>
                                            <a href="{{ route('owner.invoices.show', $invoice) }}" class="block font-label-lg text-label-lg hover:text-primary">{{ $invoice->resident?->name }}</a>
                                            <span class="font-body-sm text-body-sm text-secondary">Kamar {{ $invoice->lease?->room?->number }}</span>
                                        </td>
                                        <td class="angka">
                                            <span class="{{ $late ? 'text-error font-label-lg' : '' }}">{{ $invoice->due_date->translatedFormat('j M Y') }}</span>
                                            @if ($late)<div class="font-body-sm text-body-sm text-error">Telat {{ (int) $invoice->due_date->diffInDays($today) }} hari</div>@endif
                                        </td>
                                        <td><x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge></td>
                                        <td class="angka text-right font-label-lg">{{ App\Support\Rupiah::format($invoice->remaining()) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($finance)
                @php($maxBar = max(1, collect($finance['chart'])->flatMap(fn ($m) => [$m['income'], $m['expense']])->max()))
                <section class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col gap-space-sm">
                    <h2 class="font-headline-md text-headline-md">Pemasukan dan pengeluaran 6 bulan terakhir</h2>
                    <div class="flex items-center gap-space-md font-label-md">
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
                    <div class="flex gap-space-sm font-label-sm text-secondary" aria-hidden="true">
                        @foreach ($finance['chart'] as $month)
                            <div class="flex-1 text-center">{{ Carbon\CarbonImmutable::parse($month['month'].'-01')->locale('id')->translatedFormat('M') }}</div>
                        @endforeach
                    </div>

                    <details>
                        <summary class="cursor-pointer text-primary font-label-lg">Lihat sebagai tabel</summary>
                        <div class="table-wrap mt-space-sm">
                            <table class="table">
                                <thead><tr><th>Bulan</th><th class="text-right">Pemasukan</th><th class="text-right">Pengeluaran</th><th class="text-right">Saldo bersih</th></tr></thead>
                                <tbody>
                                    @foreach ($finance['chart'] as $month)
                                        <tr>
                                            <td>{{ Carbon\CarbonImmutable::parse($month['month'].'-01')->locale('id')->translatedFormat('F Y') }}</td>
                                            <td class="angka text-right">{{ App\Support\Rupiah::format($month['income']) }}</td>
                                            <td class="angka text-right">{{ App\Support\Rupiah::format($month['expense']) }}</td>
                                            <td class="angka text-right">{{ $month['net'] < 0 ? '−' : '' }}{{ App\Support\Rupiah::format(abs($month['net'])) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </section>
            @endif
        </div>

        <div class="xl:col-span-4 flex flex-col gap-space-md">
            <section class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col gap-space-sm">
                <div class="flex items-center justify-between">
                    <h2 class="font-headline-md text-headline-md flex items-center gap-2"><span class="material-symbols-outlined text-primary" aria-hidden="true">event_upcoming</span> Kontrak Segera Berakhir</h2>
                    <x-status-badge tone="neutral">{{ $expiringCount }} Unit</x-status-badge>
                </div>
                @forelse ($expiring as $lease)
                    @php($left = $lease->daysRemaining($today))
                    <a href="{{ route('owner.leases.show', $lease) }}" class="p-space-sm rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors flex flex-col gap-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-label-lg text-label-lg truncate">{{ $lease->primaryResident?->name }}</span>
                            <x-status-badge :tone="$left !== null && $left <= 7 ? 'danger' : 'neutral'">{{ $left }} Hari Lagi</x-status-badge>
                        </div>
                        <div class="font-body-sm text-body-sm text-secondary angka">Kamar {{ $lease->room?->number }} · berakhir {{ $lease->end_date->translatedFormat('j M Y') }}</div>
                    </a>
                @empty
                    <p class="font-body-sm text-body-sm text-secondary">Tidak ada kontrak yang berakhir dalam {{ $expiringDays }} hari.</p>
                @endforelse
            </section>

            <section class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col gap-space-sm">
                <div class="flex items-center justify-between">
                    <h2 class="font-headline-md text-headline-md flex items-center gap-2"><span class="material-symbols-outlined text-primary" aria-hidden="true">build</span> Komplain &amp; Perawatan</h2>
                    <a href="{{ route('owner.complaints.index') }}" class="font-label-sm text-label-sm text-primary font-bold hover:underline">Kelola</a>
                </div>
                @forelse ($complaints as $complaint)
                    <a href="{{ route('owner.complaints.show', $complaint) }}" class="p-space-sm rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors flex flex-col gap-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-label-sm text-label-sm text-secondary angka">#{{ $complaint->number ?? $complaint->id }}</span>
                            <x-status-badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-status-badge>
                        </div>
                        <span class="font-label-lg text-label-lg truncate">{{ $complaint->title }}</span>
                        <span class="font-body-sm text-body-sm text-secondary">{{ $complaint->resident?->name }}{{ $complaint->room ? ' (Kamar '.$complaint->room->number.')' : '' }}</span>
                    </a>
                @empty
                    <p class="font-body-sm text-body-sm text-secondary">Tidak ada komplain yang terbuka.</p>
                @endforelse
            </section>

            @if ($finance)
                <section class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col gap-space-sm">
                    <h2 class="font-headline-md text-headline-md flex items-center gap-2"><span class="material-symbols-outlined text-primary" aria-hidden="true">payments</span> Operasional Bulan Ini</h2>
                    @forelse ($finance['expenseByCategory'] as $row)
                        <div class="flex items-center justify-between font-body-md text-body-md">
                            <span class="text-on-surface-variant truncate">{{ $row->category?->name ?? 'Lainnya' }}</span>
                            <span class="font-label-lg text-label-lg angka">{{ App\Support\Rupiah::format((int) $row->total) }}</span>
                        </div>
                    @empty
                        <p class="font-body-sm text-body-sm text-secondary">Belum ada pengeluaran bulan ini.</p>
                    @endforelse
                    <div class="p-space-sm rounded-xl bg-surface-container-low flex flex-col gap-1">
                        <div class="flex items-center justify-between font-label-sm text-label-sm text-secondary uppercase"><span>Total pengeluaran</span><span class="text-error angka">{{ App\Support\Rupiah::format($finance['expenseTotal']) }}</span></div>
                        <div class="flex items-center justify-between font-label-lg text-label-lg"><span>Saldo bersih bulan ini</span><span class="font-headline-md text-headline-md angka {{ $finance['net'] < 0 ? 'text-error' : 'text-primary' }}">{{ $finance['net'] < 0 ? '−' : '' }}{{ App\Support\Rupiah::format(abs($finance['net'])) }}</span></div>
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
