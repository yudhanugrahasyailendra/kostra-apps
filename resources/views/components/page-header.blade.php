{{-- Judul halaman + aksi (pola kepala layar di docs/screens). Slot = tombol aksi. --}}
@props(['title', 'eyebrow' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col md:flex-row md:items-end md:justify-between gap-space-md']) }}>
    <div>
        @if ($eyebrow)
            <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">{{ $eyebrow }}</p>
        @endif
        <h2 class="font-headline-lg-mobile text-headline-lg-mobile lg:font-headline-lg lg:text-headline-lg">{{ $title }}</h2>
        @if ($subtitle)
            <p class="font-body-md text-body-md text-secondary">{{ $subtitle }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="flex flex-wrap items-center gap-space-sm">{{ $slot }}</div>
    @endif
</div>
