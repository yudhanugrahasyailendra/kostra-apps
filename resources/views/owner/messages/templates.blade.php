@extends('layouts.owner')

@section('title', 'Template pesan')

@section('content')
    <x-page-header eyebrow="Notifikasi" title="Template Pesan WhatsApp" />

    <nav class="flex flex-wrap gap-1 p-1.5 rounded-xl bg-surface-container-low w-fit max-w-full" aria-label="Bagian pesan">
        <a href="{{ route('owner.messages.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container">Log pengiriman</a>
        <a href="{{ route('owner.message-templates.index') }}" aria-current="page" class="px-4 py-2 rounded-lg font-label-md text-label-md bg-surface-container-lowest text-primary shadow-sm">Template pesan</a>
    </nav>

    <x-panel title="Template pesan WhatsApp">
        <p class="text-secondary mb-space-md">Semua template berkategori utility (pemberitahuan transaksi). Isi boleh diubah selama hanya memakai variabel yang tersedia; teks promosi tidak diizinkan.</p>

        <div class="flex flex-col">
            @foreach ($rows as $row)
                <div class="py-space-md border-t border-outline-variant/40 first:border-0 flex flex-wrap items-start justify-between gap-space-md">
                    <div class="min-w-0 max-w-2xl">
                        <div class="font-label-lg">{{ $row['key']->label() }}
                            @if ($row['template'] && ! $row['template']->isSystem())
                                <x-status-badge tone="neutral">Diubah</x-status-badge>
                            @endif
                            @if ($row['template'] && ! $row['template']->is_active)
                                <x-status-badge tone="warn">Nonaktif</x-status-badge>
                            @endif
                        </div>
                        <p class="font-body-sm text-secondary whitespace-pre-line mt-1">{{ $row['template']?->body ?? 'Template belum tersedia.' }}</p>
                    </div>
                    @can('update', App\Models\MessageTemplate::class)
                        <x-button :href="route('owner.message-templates.edit', $row['key']->value)" variant="secondary">Ubah</x-button>
                    @endcan
                </div>
            @endforeach
        </div>
    </x-panel>
@endsection
