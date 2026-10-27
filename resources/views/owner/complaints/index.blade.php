@extends('layouts.owner')

@section('title', 'Komplain')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-space-sm">
        <p class="text-secondary">Keluhan penghuni dilacak sampai selesai. Tiket tidak dihapus, hanya ditutup.</p>
        @can('create', App\Models\Complaint::class)
            <x-button :href="route('owner.complaints.create')">Buat tiket</x-button>
        @endcan
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Terbuka</div>
            <div class="font-currency-stat text-currency-stat angka">{{ $counts['open'] }}</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Baru</div>
            <div class="font-currency-stat text-currency-stat angka">{{ $counts['new'] }}</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Diproses</div>
            <div class="font-currency-stat text-currency-stat angka">{{ $counts['in_progress'] }}</div>
        </div>
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Selesai</div>
            <div class="font-currency-stat text-currency-stat angka">{{ $counts['done'] }}</div>
        </div>
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
            <table class="w-full text-left">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Tiket</th>
                        <th class="p-3">Kamar</th>
                        <th class="p-3 hidden md:table-cell">Kategori</th>
                        <th class="p-3 hidden md:table-cell">Penanggung jawab</th>
                        <th class="p-3">Dibuat</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($complaints as $complaint)
                        <tr class="border-t border-outline-variant/40 hover:bg-surface-container-low/50">
                            <td class="p-3">
                                <a href="{{ route('owner.complaints.show', $complaint) }}" class="font-label-lg text-primary hover:underline">{{ $complaint->title }}</a>
                                <div class="font-body-sm text-secondary">{{ $complaint->resident?->name ?? 'Penghuni dihapus' }}</div>
                            </td>
                            <td class="p-3 angka">{{ $complaint->property->name }} · {{ $complaint->room->number }}</td>
                            <td class="p-3 hidden md:table-cell">{{ $complaint->category->label() }}</td>
                            <td class="p-3 hidden md:table-cell">{{ $complaint->assignee?->name ?? 'Belum ditugaskan' }}</td>
                            <td class="p-3 angka">{{ $complaint->created_at->timezone(auth()->user()->tenant->timezone)->translatedFormat('j M Y') }}</td>
                            <td class="p-3">
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
