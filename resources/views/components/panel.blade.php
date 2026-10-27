@props(['title' => null])

<section {{ $attributes->merge(['class' => 'panel p-space-md']) }}>
    @if ($title)
        <h2 class="panel-title mb-space-md">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
