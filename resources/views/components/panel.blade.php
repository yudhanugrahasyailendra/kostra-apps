@props(['title' => null])

<section {{ $attributes->merge(['class' => 'panel p-space-md']) }}>
    @if ($title)
        <h2 class="font-headline-md text-headline-md mb-space-md">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
