@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<div class="flex flex-col gap-1">
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->merge(['class' => 'field']) }}
    >
    @if ($hint)
        <p class="font-body-sm text-body-sm text-secondary">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>
    @enderror
</div>
