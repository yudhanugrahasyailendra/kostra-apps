@extends('layouts.owner')

@section('title', $complaint->title)

@section('content')
    @php($tz = auth()->user()->tenant->timezone)

    @if ($errors->any())
        <div class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm font-label-md" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <x-panel>
        <div class="flex flex-wrap items-start justify-between gap-space-md">
            <div>
                <h2 class="font-headline-lg text-headline-lg">{{ $complaint->title }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    <x-status-badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-status-badge>
                    <x-status-badge tone="neutral">{{ $complaint->category->label() }}</x-status-badge>
                    @if ($complaint->isStale())
                        <x-status-badge tone="danger">Terbuka lebih dari {{ App\Models\Complaint::STALE_AFTER_DAYS }} hari</x-status-badge>
                    @endif
                </div>
            </div>
            <x-button :href="route('owner.complaints.index')" variant="secondary">Semua tiket</x-button>
        </div>

        <dl class="mt-space-md grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-space-sm text-body-md">
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Penghuni</dt>
                <dd>
                    @if ($complaint->resident)
                        <a class="text-primary hover:underline" href="{{ route('owner.residents.show', $complaint->resident_id) }}">{{ $complaint->resident->name }}</a>
                    @else
                        Penghuni dihapus
                    @endif
                </dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Kamar</dt><dd class="angka">{{ $complaint->property->name }} · {{ $complaint->room->number }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Dibuat</dt><dd class="angka">{{ $complaint->created_at->timezone($tz)->translatedFormat('j F Y H:i') }}</dd></div>
            <div><dt class="text-secondary font-label-sm uppercase tracking-wider">Penanggung jawab</dt><dd>{{ $complaint->assignee?->name ?? 'Belum ditugaskan' }}</dd></div>
            @if ($complaint->closed_at)
                <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Ditutup</dt>
                    <dd class="angka">{{ $complaint->closed_at->timezone($tz)->translatedFormat('j F Y H:i') }}</dd></div>
            @endif
            <div class="sm:col-span-2"><dt class="text-secondary font-label-sm uppercase tracking-wider">Deskripsi</dt><dd class="whitespace-pre-line">{{ $complaint->description }}</dd></div>
        </dl>

        @if ($complaint->attachments->isNotEmpty())
            <div class="mt-space-md flex flex-wrap gap-space-sm">
                @foreach ($complaint->attachments as $photo)
                    <a href="{{ $photo->temporaryUrl() }}" target="_blank" rel="noopener">
                        <img src="{{ $photo->temporaryUrl() }}" alt="Foto komplain: {{ $photo->original_name }}" class="h-28 rounded-lg border border-outline-variant/60">
                    </a>
                @endforeach
            </div>
        @endif
    </x-panel>

    @if ($complaint->isOpen())
        <x-panel title="Tindak lanjut">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-lg">
                <div>
                    <h3 class="font-label-md text-label-md text-secondary uppercase tracking-wider mb-space-sm">Ubah status</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($complaint->status->nextStatuses() as $next)
                            <form method="POST" action="{{ route('owner.complaints.status', $complaint) }}"
                                  @if ($next->isClosed()) onsubmit="return confirm('Tutup tiket sebagai {{ mb_strtolower($next->label()) }}? Tiket tidak bisa dibuka lagi.')" @endif>
                                @csrf
                                <input type="hidden" name="status" value="{{ $next->value }}">
                                <x-button type="submit" :variant="$next === App\Enums\ComplaintStatus::Rejected ? 'danger' : ($next->isClosed() ? 'primary' : 'secondary')">{{ match ($next) {
                                    App\Enums\ComplaintStatus::InProgress => 'Mulai proses',
                                    App\Enums\ComplaintStatus::Done => 'Tandai selesai',
                                    default => 'Tolak tiket',
                                } }}</x-button>
                            </form>
                        @endforeach
                    </div>
                </div>

                <form method="POST" action="{{ route('owner.complaints.assign', $complaint) }}" class="flex flex-col gap-space-sm">
                    @csrf
                    <x-select name="assigned_to" label="Penanggung jawab" placeholder="Belum ditugaskan">
                        @foreach ($staff as $person)
                            <option value="{{ $person->id }}" @selected($complaint->assigned_to === $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </x-select>
                    <div><x-button type="submit" variant="secondary">Simpan penanggung jawab</x-button></div>
                </form>
            </div>

            <form method="POST" action="{{ route('owner.complaints.photos.store', $complaint) }}" enctype="multipart/form-data" class="mt-space-lg flex flex-col gap-space-sm max-w-md">
                @csrf
                <x-input name="file" type="file" accept=".jpg,.jpeg,.png" label="Tambah foto (JPG atau PNG, maksimal 5 MB)" required />
                <div><x-button type="submit" variant="secondary">Unggah foto</x-button></div>
            </form>
        </x-panel>
    @endif

    <x-panel title="Percakapan">
        <ol class="flex flex-col gap-space-md">
            @forelse ($complaint->comments as $comment)
                <li class="rounded-xl border border-outline-variant/40 p-space-md {{ $comment->isByResident() ? 'bg-surface-container-low' : '' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                        <span class="font-label-lg">{{ $comment->authorName() }} <span class="font-body-sm text-secondary">· {{ $comment->isByResident() ? 'Penghuni' : 'Pengelola' }}</span></span>
                        <span class="font-body-sm text-secondary angka">{{ $comment->created_at->timezone($tz)->translatedFormat('j M Y H:i') }}</span>
                    </div>
                    <p class="whitespace-pre-line">{{ $comment->body }}</p>
                </li>
            @empty
                <li class="text-on-surface-variant">Belum ada komentar.</li>
            @endforelse
        </ol>

        @if ($complaint->isOpen())
            <form method="POST" action="{{ route('owner.complaints.comments.store', $complaint) }}" class="mt-space-md flex flex-col gap-space-sm">
                @csrf
                <div class="flex flex-col gap-1">
                    <label for="body" class="font-label-md text-label-md text-on-surface">Tulis komentar</label>
                    <textarea id="body" name="body" rows="3" maxlength="2000" required
                              class="w-full field h-auto py-2 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('body') }}</textarea>
                    @error('body')
                        <p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div><x-button type="submit">Kirim komentar</x-button></div>
            </form>
        @else
            <p class="mt-space-md text-on-surface-variant">Tiket sudah ditutup; riwayat percakapan tetap tersimpan.</p>
        @endif
    </x-panel>
@endsection
