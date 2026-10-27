@extends('layouts.owner')

@section('title', 'Komplain')

@section('content')
    <x-page-header eyebrow="Operasional kos" title="Komplain & Perawatan Fasilitas" subtitle="Keluhan penghuni dilacak sampai selesai. Tiket tidak dihapus, hanya ditutup.">
        @can('create', App\Models\Complaint::class)
            <x-button :href="route('owner.complaints.create')"><span class="material-symbols-outlined text-base" aria-hidden="true">add_task</span> Buat Tiket Manual</x-button>
        @endcan
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md">
        <x-kpi-card label="Tiket belum selesai" :value="$counts['open']" icon="assignment_late" :tone="$counts['open'] > 0 ? 'warn' : 'neutral'" hint="baru dan sedang diproses" />
        <x-kpi-card label="Baru" :value="$counts['new']" icon="fiber_new" :tone="$counts['new'] > 0 ? 'danger' : 'neutral'" hint="belum ditanggapi" />
        <x-kpi-card label="Diproses" :value="$counts['in_progress']" icon="build" hint="sedang ditangani" />
        <x-kpi-card label="Selesai" :value="$counts['done']" icon="task_alt" tone="ok" hint="tiket ditutup" />
    </div>

    <form method="GET" action="{{ route('owner.complaints.index') }}" class="panel p-space-md grid grid-cols-2 lg:grid-cols-5 gap-space-md items-end">
        <x-select name="status" label="Status">
            <option value="open" @selected($status === 'open')>Terbuka (baru dan diproses)</option>
            <option value="all" @selected($status === 'all')>Semua</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select>
        <x-select name="room_id" label="Kamar" placeholder="Semua kamar">
            @foreach ($rooms as $room)
                <option value="{{ $room->id }}" @selected((int) $roomId === $room->id)>{{ $room->property->name }} · {{ $room->number }}</option>
            @endforeach
        </x-select>
        <x-select name="category" label="Kategori" placeholder="Semua kategori">
            @foreach ($categories as $case)
                <option value="{{ $case->value }}" @selected($category === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select>
        <x-select name="assigned_to" label="Penanggung jawab" placeholder="Semua">
            <option value="me" @selected($assignedTo === 'me')>Tiket saya</option>
            <option value="none" @selected($assignedTo === 'none')>Belum ditugaskan</option>
            @foreach ($staff as $person)
                <option value="{{ $person->id }}" @selected((string) $assignedTo === (string) $person->id)>{{ $person->name }}</option>
            @endforeach
        </x-select>
        <div class="flex gap-2">
            <x-button type="submit">Terapkan</x-button>
            <x-button :href="route('owner.complaints.index')" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    <x-panel>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tiket</th>
                        <th>Kamar</th>
                        <th class="hidden md:table-cell">Kategori</th>
                        <th class="hidden md:table-cell">Penanggung jawab</th>
                        <th>Dibuat</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($complaints as $complaint)
                        <tr>
                            <td>
                                <a href="{{ route('owner.complaints.show', $complaint) }}" class="font-label-lg text-primary hover:underline">{{ $complaint->title }}</a>
                                <div class="font-body-sm text-secondary">{{ $complaint->resident?->name ?? 'Penghuni dihapus' }}</div>
                            </td>
                            <td class="angka">{{ $complaint->property->name }} · {{ $complaint->room->number }}</td>
                            <td class="hidden md:table-cell">{{ $complaint->category->label() }}</td>
                            <td class="hidden md:table-cell">{{ $complaint->assignee?->name ?? 'Belum ditugaskan' }}</td>
                            <td class="angka">{{ $complaint->created_at->timezone(auth()->user()->tenant->timezone)->translatedFormat('j M Y') }}</td>
                            <td>
                                <x-status-badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-status-badge>
                                @if ($complaint->isStale())
                                    <x-status-badge tone="danger">Terbuka lebih dari {{ App\Models\Complaint::STALE_AFTER_DAYS }} hari</x-status-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4 text-on-surface-variant">Tidak ada tiket yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{ $complaints->links() }}
@endsection
