{{-- Kartu angka (KPI). $tone mewarnai ikon dan nilai: neutral | ok | warn | danger --}}
@props(['label', 'value', 'icon' => null, 'tone' => 'neutral', 'hint' => null])

@php
    $iconClass = ['neutral' => 'bg-secondary-container text-on-secondary-container', 'ok' => 'bg-primary-fixed text-on-primary-fixed-variant', 'warn' => 'bg-tertiary-fixed text-on-tertiary-fixed', 'danger' => 'bg-error-container text-on-error-container'][$tone] ?? '';
    $valueClass = ['danger' => 'text-error', 'ok' => 'text-primary', 'warn' => 'text-tertiary'][$tone] ?? 'text-on-surface';
@endphp

<div {{ $attributes->merge(['class' => 'p-space-md rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-sm']) }}>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="kpi-label">{{ $label }}</div>
            <div class="font-currency-stat text-currency-stat angka {{ $valueClass }}">{{ $value }}</div>
        </div>
        @if ($icon)
            <span class="kpi-icon {{ $iconClass }}" aria-hidden="true"><span class="material-symbols-outlined">{{ $icon }}</span></span>
        @endif
    </div>
    @if ($hint)
        <div class="font-body-sm text-body-sm text-secondary">{{ $hint }}</div>
    @endif
    {{ $slot }}
</div>
