@props(['name', 'label', 'selected' => null, 'placeholder' => null])

<div class="flex flex-col gap-1">
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'field']) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>
    @error($name)
        <p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>
    @enderror
</div>
