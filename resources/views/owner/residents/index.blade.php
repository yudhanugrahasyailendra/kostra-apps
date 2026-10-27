@extends('layouts.owner')

@section('title', 'Penghuni')

@section('content')
    <x-page-header eyebrow="Modul operasional" title="Daftar Penghuni & Kontrak Sewa" subtitle="Kelola data penyewa, kontrak, dan deposit yang tercatat.">
        @can('create', App\Models\Resident::class)
            <x-button type="button" variant="secondary" data-modal-open="modal-import"><span class="material-symbols-outlined text-base" aria-hidden="true">upload_file</span> Impor CSV</x-button>
            <x-button :href="route('owner.residents.create')"><span class="material-symbols-outlined text-base" aria-hidden="true">person_add</span> Tambah Penghuni Baru</x-button>
        @endcan
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <x-kpi-card label="Penghuni aktif" :value="$stats['active']" icon="task_alt" tone="ok" :hint="'dari '.$stats['rooms'].' kamar terdaftar'" />
        <x-kpi-card label="Segera berakhir (≤ 14 hari)" :value="$stats['expiring']" icon="schedule" tone="warn" hint="kontrak perlu diperpanjang" />
        <x-kpi-card label="Menunggak sewa" :value="$stats['overdueLeases']" icon="event_busy" :tone="$stats['overdueLeases'] > 0 ? 'danger' : 'neutral'" hint="kontrak dengan tagihan lewat tempo" />
        <x-kpi-card label="Total deposit tercatat" :value="App\Support\Rupiah::format($stats['deposits'])" icon="savings" hint="dicatat, bukan ditahan Kostra" />
    </div>

    <form method="GET" action="{{ route('owner.residents.index') }}" class="panel p-space-md flex flex-wrap items-end gap-space-sm">
        <div class="flex-1 min-w-56"><x-input name="q" label="Cari nama, nomor, atau kamar" :value="$q" placeholder="Cari nama penghuni, nomor WhatsApp, nomor kamar..." /></div>
        <x-select name="status" label="Status" placeholder="Semua">
            <option value="active" @selected($status === 'active')>Aktif</option>
            <option value="inactive" @selected($status === 'inactive')>Tidak aktif</option>
        </x-select>
        <x-button type="submit"><span class="material-symbols-outlined text-base" aria-hidden="true">search</span> Cari</x-button>
        <x-button :href="route('owner.residents.index')" variant="secondary">Atur ulang</x-button>
    </form>

    @if (session('import_failures'))
        <x-panel title="Baris impor yang gagal">
            <table class="table">
                <thead>
                    <tr><th>Baris</th><th>Nama</th><th>Alasan</th></tr>
                </thead>
                <tbody>
                    @foreach (session('import_failures') as $failure)
                        <tr>
                            <td class="angka">{{ $failure['line'] }}</td>
                            <td>{{ $failure['name'] ?: '—' }}</td>
                            <td>{{ $failure['reason'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
    @endif

    @if (session('import_duplicates'))
        <x-panel title="Nomor ganda (tetap diimpor, mohon diperiksa)">
            <ul class="text-body-sm list-disc pl-5">
                @foreach (session('import_duplicates') as $duplicate)
                    <li>Baris <span class="angka">{{ $duplicate['line'] }}</span>: {{ $duplicate['name'] }} · <span class="angka">{{ $duplicate['phone'] }}</span></li>
                @endforeach
            </ul>
        </x-panel>
    @endif

    <x-panel>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Nomor WhatsApp</th>
                        <th>Kamar</th>
                        <th class="hidden sm:table-cell">Pekerjaan / kampus</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($residents as $resident)
                        <tr>
                            <td class="font-label-lg">
                                <a href="{{ route('owner.residents.show', $resident) }}" class="text-primary hover:underline">{{ $resident->name }}</a>
                            </td>
                            <td class="angka">
                                {{ $resident->phone }}
                                @if ($sharedPhones->has($resident->phone))
                                    <x-status-badge tone="warn">Nomor ganda</x-status-badge>
                                @endif
                            </td>
                            <td>
                                @forelse ($resident->leases as $lease)
                                    <a href="{{ route('owner.leases.show', $lease) }}" class="text-primary hover:underline">{{ $lease->room->number }}</a>@unless ($loop->last), @endunless
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="hidden sm:table-cell">{{ $resident->occupation ?? '—' }}</td>
                            <td>
                                <x-status-badge :tone="$resident->is_active ? 'ok' : 'neutral'">{{ $resident->is_active ? 'Aktif' : 'Tidak aktif' }}</x-status-badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-on-surface-variant">Tidak ada penghuni yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{ $residents->links() }}

    @can('create', App\Models\Resident::class)
        <x-modal id="modal-import" title="Impor penghuni dari CSV">
            <form method="POST" action="{{ route('owner.residents.import') }}" enctype="multipart/form-data" class="flex flex-col gap-space-md">
                @csrf
                <input type="hidden" name="_modal" value="modal-import">
                <x-input name="file" type="file" accept=".csv,text/csv" label="Berkas CSV" required />
                <p class="text-body-sm text-secondary">
                    Baris pertama berisi nama kolom: <code>nama</code> dan <code>telepon</code> (wajib), serta <code>email</code>, <code>pekerjaan</code>,
                    <code>kontak_darurat_nama</code>, <code>kontak_darurat_hubungan</code>, <code>kontak_darurat_telepon</code>, <code>catatan</code>.
                    Nomor ganda tetap diimpor dan ditandai. Maksimal 5 MB.
                </p>
                <label class="flex items-start gap-2 text-body-md">
                    <input type="checkbox" name="consent" value="1" class="rounded border-outline-variant mt-1">
                    <span>Seluruh penghuni di berkas ini sudah menyetujui datanya dikumpulkan dan disimpan di Kostra.</span>
                </label>
                @error('consent')
                    <p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>
                @enderror
                <div><x-button type="submit">Impor</x-button></div>
            </form>
        </x-modal>
    @endcan

    @if (old('_modal'))
        <script>document.getElementById(@json(old('_modal')))?.showModal();</script>
    @endif
@endsection
