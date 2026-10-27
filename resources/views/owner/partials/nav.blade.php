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

<a href="{{ route('owner.rooms.index') }}" @if (request()->routeIs('owner.rooms.*', 'owner.properties.*', 'owner.room-types.*')) aria-current="page" @endif
   class="{{ $link }} {{ request()->routeIs('owner.rooms.*', 'owner.properties.*', 'owner.room-types.*') ? $active : $idle }}">
    <span class="material-symbols-outlined text-lg" aria-hidden="true">meeting_room</span>
    <span>Properti &amp; Kamar</span>
</a>

<a href="{{ route('owner.residents.index') }}" @if (request()->routeIs('owner.residents.*')) aria-current="page" @endif
   class="{{ $link }} {{ request()->routeIs('owner.residents.*') ? $active : $idle }}">
    <span class="material-symbols-outlined text-lg" aria-hidden="true">group</span>
    <span>Penghuni</span>
</a>

<a href="{{ route('owner.leases.index') }}" @if (request()->routeIs('owner.leases.*')) aria-current="page" @endif
   class="{{ $link }} {{ request()->routeIs('owner.leases.*') ? $active : $idle }}">
    <span class="material-symbols-outlined text-lg" aria-hidden="true">assignment</span>
    <span>Kontrak</span>
</a>

<a href="{{ route('owner.invoices.index') }}" @if (request()->routeIs('owner.invoices.*')) aria-current="page" @endif
   class="{{ $link }} {{ request()->routeIs('owner.invoices.*') ? $active : $idle }}">
    <span class="material-symbols-outlined text-lg" aria-hidden="true">receipt_long</span>
    <span>Tagihan</span>
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
