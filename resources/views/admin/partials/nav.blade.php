@php
    $link = 'flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all';
    $active = 'bg-primary text-on-primary font-label-lg';
    $idle = 'font-body-md text-on-surface-variant hover:bg-surface-container hover:text-on-surface';
    $items = [
        ['admin.dashboard', 'Ringkasan', 'dashboard', ['admin.dashboard']],
        ['admin.tenants.index', 'Tenant', 'apartment', ['admin.tenants.*']],
        ['admin.plans.index', 'Paket', 'workspace_premium', ['admin.plans.*']],
    ];
@endphp

@foreach ($items as [$route, $label, $icon, $patterns])
    @php($current = request()->routeIs(...$patterns))
    <a href="{{ route($route) }}" @if ($current) aria-current="page" @endif class="{{ $link }} {{ $current ? $active : $idle }}">
        <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ $icon }}</span>
        <span>{{ $label }}</span>
    </a>
@endforeach
