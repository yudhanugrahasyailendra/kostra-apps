@extends('layouts.portal')

@section('title', $complaint->title)

@section('content')
    <a href="{{ route('portal.complaints.index', ['token' => $portalToken]) }}" class="text-primary">&larr; Komplain</a>

    <section class="panel p-space-md">
        <div class="flex items-start justify-between gap-space-sm">
            <h1 class="font-headline-md text-headline-md min-w-0">{{ $complaint->title }}</h1>
            <x-status-badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-status-badge>
        </div>
        <div class="font-body-sm text-secondary mt-1">{{ $complaint->category->label() }} · Kamar {{ $complaint->room?->number }} · {{ $complaint->created_at->timezone($portalTenant->timezone)->translatedFormat('j M Y') }}</div>
        <p class="mt-space-sm whitespace-pre-line">{{ $complaint->description }}</p>
        @if (! $complaint->isOpen())
            <p class="mt-space-sm font-body-sm text-secondary">Tiket ini sudah ditutup.</p>
        @endif
    </section>

    <section class="panel p-space-md">
        <h2 class="font-headline-md text-headline-md mb-space-sm">Percakapan</h2>
        <ol class="flex flex-col gap-space-sm">
            @forelse ($complaint->comments as $comment)
                <li class="rounded-xl border border-outline-variant/40 p-space-sm {{ $comment->isByResident() ? 'bg-surface-container-low' : '' }}">
                    <div class="font-label-md">{{ $comment->isByResident() ? 'Anda' : 'Pengelola' }}
                        <span class="font-body-sm text-secondary angka">· {{ $comment->created_at->timezone($portalTenant->timezone)->translatedFormat('j M H:i') }}</span></div>
                    <p class="whitespace-pre-line">{{ $comment->body }}</p>
                </li>
            @empty
                <li class="text-on-surface-variant">Belum ada komentar.</li>
            @endforelse
        </ol>

        @if ($complaint->isOpen())
            <form method="POST" action="{{ route('portal.complaints.comments.store', ['token' => $portalToken, 'complaint' => $complaint->id]) }}" class="mt-space-md flex flex-col gap-space-sm">
                @csrf
                <label for="body" class="font-label-md text-label-md">Tulis komentar</label>
                <textarea id="body" name="body" rows="3" maxlength="2000" required
                          class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 py-2 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('body') }}</textarea>
                <x-button type="submit">Kirim komentar</x-button>
            </form>
        @endif
    </section>
@endsection
