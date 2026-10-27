@extends('layouts.owner')

@section('title', 'Pesan WhatsApp')

@section('content')
    @php($tz = auth()->user()->tenant->timezone)

    <x-page-header eyebrow="Notifikasi" title="Pengaturan & Log Notifikasi WhatsApp" subtitle="Pengingat tagihan dan kuitansi otomatis, dengan pemantauan kuota pesan bulanan." />

    <nav class="flex flex-wrap gap-1 p-1.5 rounded-xl bg-surface-container-low w-fit max-w-full" aria-label="Bagian pesan">
        <a href="{{ route('owner.messages.index') }}" aria-current="page" class="px-4 py-2 rounded-lg font-label-md text-label-md bg-surface-container-lowest text-primary shadow-sm">Log pengiriman</a>
        <a href="{{ route('owner.message-templates.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container">Template pesan</a>
    </nav>

    @if ($quota['remaining'] === 0)
        <div class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm font-label-md" role="alert">
            Kuota pesan {{ $quota['period'] }} habis ({{ $quota['used'] }} dari {{ $quota['limit'] }}). Pesan otomatis dilewati sampai bulan depan atau sampai paket dinaikkan.
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col gap-space-sm">
            <div class="flex items-start justify-between">
                <div>
                    <div class="kpi-label">Sisa kuota {{ $quota['period'] }}</div>
                    <div class="font-currency-stat text-currency-stat angka" data-stat="quota-remaining">{{ $quota['remaining'] }}</div>
                </div>
                <span class="kpi-icon bg-secondary-container text-on-secondary-container" aria-hidden="true"><span class="material-symbols-outlined">chat</span></span>
            </div>
            <div class="meter" role="img" aria-label="Kuota terpakai {{ $quota['used'] }} dari {{ $quota['limit'] }}"><span style="width: {{ $quota['limit'] > 0 ? min(100, round($quota['used'] / $quota['limit'] * 100)) : 0 }}%"></span></div>
            <div class="font-body-sm text-body-sm text-secondary">{{ $quota['used'] }} terpakai dari {{ $quota['limit'] }} pesan</div>
        </div>
        <x-kpi-card label="Terkirim" :value="$counts['sent'] + $counts['delivered']" icon="mark_email_read" tone="ok" hint="berhasil dikirim" />
        <x-kpi-card label="Gagal" :value="$counts['failed']" icon="error" :tone="$counts['failed'] > 0 ? 'danger' : 'neutral'" hint="perlu dicek atau dikirim ulang" />
        <x-kpi-card label="Dilewati" :value="$counts['skipped']" icon="do_not_disturb_on" hint="kuota habis atau penghuni berhenti" />
    </div>

    <form method="GET" action="{{ route('owner.messages.index') }}" class="panel p-space-md flex flex-wrap items-end gap-space-md">
        <x-select name="status" label="Status">
            <option value="all" @selected($status === 'all')>Semua</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select>
        <div class="flex gap-2">
            <x-button type="submit">Terapkan</x-button>
            <x-button :href="route('owner.messages.index')" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    <x-panel>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Penerima</th>
                        <th>Template</th>
                        <th>Status</th>
                        <th><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-outline-variant/40 align-top">
                            <td class="angka">{{ $log->created_at->timezone($tz)->translatedFormat('j M Y H:i') }}</td>
                            <td>
                                {{ $log->resident?->name ?? '—' }}
                                <div class="font-body-sm text-secondary angka">…{{ substr($log->recipient, -4) }}</div>
                            </td>
                            <td>
                                {{ $log->template?->name ?? '—' }}
                                <details class="font-body-sm text-secondary">
                                    <summary class="cursor-pointer text-primary">Lihat isi pesan</summary>
                                    <p class="whitespace-pre-line mt-1">{{ $log->displayBody() }}</p>
                                </details>
                            </td>
                            <td>
                                <x-status-badge :tone="$log->status->tone()">{{ $log->status->label() }}</x-status-badge>
                                @if ($log->skip_reason)
                                    <div class="font-body-sm text-secondary">{{ $log->skip_reason->label() }}</div>
                                @endif
                                @if ($log->status === App\Enums\MessageStatus::Failed)
                                    <div class="font-body-sm text-secondary">Kode galat: {{ $log->error_code }} · {{ $log->attempts }}× percobaan</div>
                                @endif
                                @if ($log->status === App\Enums\MessageStatus::Queued && $log->scheduled_for)
                                    <div class="font-body-sm text-secondary angka">Dijadwalkan {{ $log->scheduled_for->timezone($tz)->translatedFormat('j M H:i') }}</div>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($log->canResend())
                                    <form method="POST" action="{{ route('owner.messages.resend', $log) }}">
                                        @csrf
                                        <x-button type="submit" variant="secondary">Kirim ulang</x-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-on-surface-variant">Belum ada pesan yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{ $logs->links() }}
@endsection
