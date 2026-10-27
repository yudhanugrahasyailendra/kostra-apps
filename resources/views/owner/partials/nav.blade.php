@php
    $link = 'flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all';
    $active = 'bg-primary text-on-primary font-label-lg';
    $idle = 'font-body-md text-on-surface-variant hover:bg-surface-container hover:text-on-surface';
    $soon = 'font-body-md text-on-surface-variant opacity-60 cursor-not-allowed';
@endphp

<a href="{{ route('owner.dashboard') }}" @if (request()->routeIs('owner.dashboard')) aria-current="page" @endif
   class="{{ $link }} {{ request()->routeIs('owner.dashboard') ? $active : $idle }}">
    <span class="material-symbols-outlined text-lg" aria-hidden="true">dashboard</span>
    <span>Ringkasan</span>
</a>

@can('finance.view')
    <span class="{{ $link }} {{ $soon }}" aria-disabled="true">
        <span class="material-symbols-outlined text-lg" aria-hidden="true">account_balance_wallet</span>
        <span>Keuangan</span>
        <span class="badge badge-neutral ml-auto">Segera</span>
    </span>
@endcan

@can('settings.manage')
    <a href="{{ route('owner.settings') }}" @if (request()->routeIs('owner.settings', 'owner.staff.*')) aria-current="page" @endif
       class="{{ $link }} {{ request()->routeIs('owner.settings', 'owner.staff.*') ? $active : $idle }}">
        <span class="material-symbols-outlined text-lg" aria-hidden="true">settings</span>
        <span>Pengaturan</span>
    </a>
@endcan

@can('subscription.manage')
    <span class="{{ $link }} {{ $soon }}" aria-disabled="true">
        <span class="material-symbols-outlined text-lg" aria-hidden="true">workspace_premium</span>
        <span>Langganan</span>
        <span class="badge badge-neutral ml-auto">Segera</span>
    </span>
@endcan
