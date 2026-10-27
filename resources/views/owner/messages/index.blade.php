@extends('layouts.owner')

@section('title', 'Pesan WhatsApp')

@section('content')
    @php($tz = auth()->user()->tenant->timezone)

    <nav class="flex flex-wrap gap-2" aria-label="Bagian pesan">
        <a href="{{ route('owner.messages.index') }}" aria-current="page" class="btn btn-primary">Log pengiriman</a>
        <a href="{{ route('owner.message-templates.index') }}" class="btn btn-secondary">Template pesan</a>
    </nav>

    @if ($quota['remaining'] === 0)
        <div class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm font-label-md" role="alert">
            Kuota pesan {{ $quota['period'] }} habis ({{ $quota['used'] }} dari {{ $quota['limit'] }}). Pesan otomatis dilewati sampai bulan depan atau sampai paket dinaikkan.
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Sisa kuota {{ $quota['period'] }}</div>
            <div class="font-currency-stat text-currency-stat angka" data-stat="quota-remaining">{{ $quota['remaining'] }}</div>
            <div class="font-body-sm text-secondary">{{ $quota['used'] }} terpakai dari {{ $quota['limit'] }} pesan</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Terkirim</div>
            <div class="font-currency-stat text-currency-stat angka">{{ $counts['sent'] + $counts['delivered'] }}</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Gagal</div>
            <div class="font-currency-stat text-currency-stat angka">{{ $counts['failed'] }}</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Dilewati</div>
            <div class="font-currency-stat text-currency-stat angka">{{ $counts['skipped'] }}</div>
        </div>
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
            <table class="w-full text-left">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Waktu</th>
                        <th class="p-3">Penerima</th>
                        <th class="p-3">Template</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-outline-variant/40 align-top">
                            <td class="p-3 angka">{{ $log->created_at->timezone($tz)->translatedFormat('j M Y H:i') }}</td>
                            <td class="p-3">
                                {{ $log->resident?->name ?? '—' }}
                                <div class="font-body-sm text-secondary angka">…{{ substr($log->recipient, -4) }}</div>
                            </td>
                            <td class="p-3">
                                {{ $log->template?->name ?? '—' }}
                                <details class="font-body-sm text-secondary">
                                    <summary class="cursor-pointer text-primary">Lihat isi pesan</summary>
                                    <p class="whitespace-pre-line mt-1">{{ $log->body }}</p>
                                </details>
                            </td>
                            <td class="p-3">
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
                            <td class="p-3 text-right">
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
