{{-- Sel denah kamar. Dilengkapi di Fase 2. $tone: ok | warn | danger | neutral --}}
@props(['number', 'label' => null, 'tone' => 'neutral'])

<div {{ $attributes->merge(['class' => 'p-2 rounded-lg text-center badge-'.$tone]) }}>
    <div class="font-label-md text-label-md font-bold angka">{{ $number }}</div>
    @if ($label)
        <div class="font-label-sm text-label-sm">{{ $label }}</div>
    @endif
</div>
