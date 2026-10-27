@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<div class="flex flex-col gap-1">
    <label for="{{ $name }}" class="font-label-md text-label-md text-on-surface">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 h-12 lg:h-10 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary']) }}
    >
    @if ($hint)
        <p class="font-body-sm text-body-sm text-secondary">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>
    @enderror
</div>
