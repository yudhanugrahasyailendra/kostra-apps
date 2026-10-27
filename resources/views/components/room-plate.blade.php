{{-- Sel denah kamar. Status selalu teks + warna. $tone: ok | warn | danger | neutral | maintenance --}}
@props(['number', 'label', 'tone' => 'neutral', 'sub' => null, 'href' => null])

@php
    $class = 'block p-2 rounded-lg text-center badge-'.$tone;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>
        <div class="font-label-md text-label-md font-bold angka">{{ $number }}</div>
        <div class="font-label-sm text-label-sm uppercase tracking-wider">{{ $label }}</div>
        @if ($sub)
            <div class="font-body-sm text-body-sm truncate">{{ $sub }}</div>
        @endif
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->merge(['class' => $class]) }}>
        <div class="font-label-md text-label-md font-bold angka">{{ $number }}</div>
        <div class="font-label-sm text-label-sm uppercase tracking-wider">{{ $label }}</div>
        @if ($sub)
            <div class="font-body-sm text-body-sm truncate">{{ $sub }}</div>
        @endif
        {{ $slot }}
    </div>
@endif
