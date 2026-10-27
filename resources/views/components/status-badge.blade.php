{{-- Status selalu teks + warna. $tone: ok | warn | danger | neutral --}}
@props(['tone' => 'neutral'])

<span {{ $attributes->merge(['class' => 'badge badge-'.$tone]) }}>
    <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $slot }}
</span>
