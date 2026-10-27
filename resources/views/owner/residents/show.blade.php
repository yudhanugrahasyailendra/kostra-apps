@extends('layouts.owner')

@section('title', $resident->name)

@section('content')
    <x-panel>
        <div class="flex flex-wrap items-start justify-between gap-space-md">
            <div class="min-w-0">
                <h2 class="font-headline-lg text-headline-lg">{{ $resident->name }}</h2>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <x-status-badge :tone="$resident->is_active ? 'ok' : 'neutral'">{{ $resident->is_active ? 'Aktif' : 'Tidak aktif' }}</x-status-badge>
                    @if ($sharesPhone)
                        <x-status-badge tone="warn">Nomor ganda</x-status-badge>
                    @endif
                    @if ($resident->whatsapp_opt_out_at)
                        <x-status-badge tone="danger">Berhenti menerima pesan</x-status-badge>
                    @endif
                </div>
            </div>
            @can('update', $resident)
                <x-button :href="route('owner.residents.edit', $resident)" variant="secondary">Ubah</x-button>
            @endcan
        </div>

        <dl class="mt-space-md grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-space-sm text-body-md">
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Nomor WhatsApp</dt><dd class="angka">{{ $resident->phone }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Email</dt><dd>{{ $resident->email ?? '—' }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Pekerjaan / kampus</dt><dd>{{ $resident->occupation ?? '—' }}</dd></div>
            <div>
                <dt class="text-secondary font-label-sm uppercase tracking-wider">Kontak darurat</dt>
                <dd>
                    @if ($resident->emergency_name || $resident->emergency_phone)
                        {{ $resident->emergency_name }} @if ($resident->emergency_relation)({{ $resident->emergency_relation }})@endif
                        <span class="angka">{{ $resident->emergency_phone }}</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Catatan internal</dt><dd>{{ $resident->notes ?? '—' }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Persetujuan data</dt><dd>{{ $resident->consented_at?->timezone(auth()->user()->tenant->timezone)->translatedFormat('j F Y') ?? 'Belum tercatat' }}</dd></div>
        </dl>
    </x-panel>

    @can('update', $resident)
        <x-panel title="Pesan WhatsApp otomatis">
            <p class="text-body-md text-on-surface-variant mb-space-md">
                @if ($resident->whatsapp_opt_out_at)
                    Penghuni berhenti menerima pesan otomatis sejak {{ $resident->whatsapp_opt_out_at->timezone(auth()->user()->tenant->timezone)->translatedFormat('j F Y') }}. Tagihan dan pengingat tidak dikirim lewat WhatsApp.
                @else
                    Penghuni menerima tagihan, pengingat, dan kuitansi lewat WhatsApp. Catat bila penghuni meminta berhenti; berlaku seketika, termasuk untuk pesan yang sudah antre.
                @endif
            </p>
            <form method="POST" action="{{ route('owner.residents.whatsapp', $resident) }}">
                @csrf
                <input type="hidden" name="opt_out" value="{{ $resident->whatsapp_opt_out_at ? 0 : 1 }}">
                <x-button type="submit" :variant="$resident->whatsapp_opt_out_at ? 'primary' : 'secondary'">{{ $resident->whatsapp_opt_out_at ? 'Aktifkan kembali pesan otomatis' : 'Catat berhenti menerima pesan' }}</x-button>
            </form>
        </x-panel>
    @endcan

    <x-panel title="Riwayat kontrak">
        @forelse ($leases as $lease)
            <a href="{{ route('owner.leases.show', $lease) }}" class="flex flex-wrap items-center justify-between gap-2 py-space-sm border-b border-outline-variant/40 last:border-0 hover:bg-surface-container-low/50">
                <div>
                    <div class="font-label-lg">Kamar <span class="angka">{{ $lease->room->number }}</span> · {{ $lease->room->property->name }}</div>
                    <div class="font-body-sm text-secondary">
                        {{ $lease->start_date->translatedFormat('j M Y') }} s/d {{ $lease->end_date?->translatedFormat('j M Y') ?? 'tanpa tanggal akhir' }}
                    </div>
                </div>
                <x-status-badge :tone="$lease->status->tone()">{{ $lease->status->label() }}</x-status-badge>
            </a>
        @empty
            <p class="text-on-surface-variant">Belum ada kontrak.</p>
        @endforelse
        @can('create', App\Models\Lease::class)
            @if ($resident->is_active || $leases->isEmpty())
                <div class="mt-space-md">
                    <x-button :href="route('owner.leases.create', ['resident_id' => $resident->id])" variant="secondary">Check-in ke kamar</x-button>
                </div>
            @endif
        @endcan
    </x-panel>

    <x-panel title="Dokumen">
        @forelse ($attachments as $attachment)
            <div class="py-space-sm border-b border-outline-variant/40 last:border-0 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-label-lg">{{ $attachment->category->label() }}
                        @if ($attachment->is_sensitive)
                            <x-status-badge tone="warn">Sensitif · terenkripsi</x-status-badge>
                        @endif
                    </div>
                    <div class="font-body-sm text-secondary">Diunggah {{ $attachment->created_at->timezone(auth()->user()->tenant->timezone)->translatedFormat('j F Y H:i') }}</div>
                </div>
                @can('view', $attachment)
                    <div class="flex gap-2">
                        <x-button :href="$attachment->temporaryUrl()" variant="secondary" target="_blank" rel="noopener">Lihat</x-button>
                        <form method="POST" action="{{ route('owner.attachments.destroy', $attachment) }}" onsubmit="return confirm('Hapus dokumen ini?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger">Hapus</x-button>
                        </form>
                    </div>
                @else
                    <span class="font-body-sm text-secondary">Perlu izin dokumen sensitif</span>
                @endcan
            </div>
        @empty
            <p class="text-on-surface-variant">Belum ada dokumen.</p>
        @endforelse

        @can('update', $resident)
            <form method="POST" action="{{ route('owner.residents.attachments.store', $resident) }}" enctype="multipart/form-data" class="mt-space-md flex flex-col gap-space-md border-t border-outline-variant/40 pt-space-md">
                @csrf
                <x-select name="category" label="Jenis dokumen">
                    @foreach ($categories as $value)
                        @php($category = App\Enums\AttachmentCategory::from($value))
                        @can('upload', [App\Models\Attachment::class, $category])
                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $category->label() }}</option>
                        @endcan
                    @endforeach
                </x-select>
                <x-input name="file" type="file" accept=".jpg,.jpeg,.png,.pdf" label="Berkas (JPG, PNG, atau PDF, maksimal 5 MB)" required />
                <p class="text-body-sm text-on-tertiary-fixed-variant bg-tertiary-fixed rounded-lg px-3 py-2">
                    Foto KTP memuat data pribadi. Unggah hanya bila benar-benar perlu dan dengan persetujuan penghuni.
                    Berkas disimpan terenkripsi, dan setiap pembukaannya dicatat. Nomor KTP tidak disimpan di Kostra.
                </p>
                <div><x-button type="submit">Unggah</x-button></div>
            </form>
        @endcan
    </x-panel>

    @can('delete', $resident)
        <x-panel title="Hapus data penghuni">
            <p class="text-body-md text-on-surface-variant mb-space-md">
                Data pribadi dan seluruh dokumen akan dihapus permanen. Catatan keuangan tetap tersimpan tanpa identitas. Tindakan ini tidak bisa dibatalkan.
            </p>
            <form method="POST" action="{{ route('owner.residents.destroy', $resident) }}" onsubmit="return confirm('Anonimkan dan hapus data penghuni ini secara permanen?')">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger">Anonimkan dan hapus</x-button>
            </form>
        </x-panel>
    @endcan
@endsection
