@extends('layouts.owner')

@section('title', 'Penghuni')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-space-sm">
        <form method="GET" action="{{ route('owner.residents.index') }}" class="flex flex-wrap items-end gap-space-sm">
            <x-input name="q" label="Cari nama atau nomor" :value="$q" />
            <x-select name="status" label="Status" placeholder="Semua">
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Tidak aktif</option>
            </x-select>
            <x-button type="submit">Cari</x-button>
            <x-button :href="route('owner.residents.index')" variant="secondary">Atur ulang</x-button>
        </form>
        @can('create', App\Models\Resident::class)
            <div class="flex gap-space-sm">
                <x-button type="button" variant="secondary" data-modal-open="modal-import">Impor CSV</x-button>
                <x-button :href="route('owner.residents.create')">Tambah penghuni</x-button>
            </div>
        @endcan
    </div>

    @if (session('import_failures'))
        <x-panel title="Baris impor yang gagal">
            <table class="w-full text-left text-body-sm">
                <thead class="bg-surface-container-low text-secondary font-label-sm uppercase tracking-wider">
                    <tr><th class="p-2">Baris</th><th class="p-2">Nama</th><th class="p-2">Alasan</th></tr>
                </thead>
                <tbody>
                    @foreach (session('import_failures') as $failure)
                        <tr class="border-t border-outline-variant/40">
                            <td class="p-2 angka">{{ $failure['line'] }}</td>
                            <td class="p-2">{{ $failure['name'] ?: '—' }}</td>
                            <td class="p-2">{{ $failure['reason'] }}</td>
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
            <table class="w-full text-left">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Nama</th>
                        <th class="p-3">Nomor WhatsApp</th>
                        <th class="p-3 hidden sm:table-cell">Pekerjaan / kampus</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($residents as $resident)
                        <tr class="border-t border-outline-variant/40 hover:bg-surface-container-low/50">
                            <td class="p-3 font-label-lg">
                                <a href="{{ route('owner.residents.show', $resident) }}" class="text-primary hover:underline">{{ $resident->name }}</a>
                            </td>
                            <td class="p-3 angka">
                                {{ $resident->phone }}
                                @if ($sharedPhones->has($resident->phone))
                                    <x-status-badge tone="warn">Nomor ganda</x-status-badge>
                                @endif
                            </td>
                            <td class="p-3 hidden sm:table-cell">{{ $resident->occupation ?? '—' }}</td>
                            <td class="p-3">
                                <x-status-badge :tone="$resident->is_active ? 'ok' : 'neutral'">{{ $resident->is_active ? 'Aktif' : 'Tidak aktif' }}</x-status-badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4 text-on-surface-variant">Tidak ada penghuni yang cocok.</td></tr>
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
