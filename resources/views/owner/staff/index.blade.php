@extends('layouts.owner')

@section('title', 'Staf')

@section('content')
    @if (session('invite_link'))
        <div class="rounded-xl bg-tertiary-fixed text-on-tertiary-fixed px-space-md py-space-sm" role="status">
            <p class="font-label-md mb-1">Tautan undangan (hanya ditampilkan sekali):</p>
            <input type="text" readonly value="{{ session('invite_link') }}" class="w-full rounded-lg bg-surface-container-lowest px-3 h-10 text-body-sm" onclick="this.select()">
        </div>
    @endif

    <x-panel title="Undang staf">
        <form method="POST" action="{{ route('owner.staff.invite') }}" class="flex flex-col gap-space-md">
            @csrf
            <x-input name="email" type="email" label="Email" />
            <x-input name="phone" type="tel" label="Nomor WhatsApp" hint="Isi email atau nomor WhatsApp." />

            <fieldset class="flex flex-col gap-2">
                <legend class="font-label-md mb-1">Izin tambahan</legend>
                @foreach (App\Enums\Permission::cases() as $permission)
                    <label class="flex items-center gap-2 text-body-md">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" class="rounded border-outline-variant">
                        {{ $permission->label() }}
                    </label>
                @endforeach
            </fieldset>

            <div><x-button type="submit">Kirim undangan</x-button></div>
        </form>
    </x-panel>

    <x-panel title="Staf">
        @forelse ($staff as $member)
            <form method="POST" action="{{ route('owner.staff.update', $member) }}" class="py-space-sm border-b border-outline-variant/40 last:border-0 flex flex-col gap-2">
                @csrf
                @method('PUT')
                <div class="font-label-lg">{{ $member->name }} <span class="text-secondary font-body-sm">{{ $member->email ?? $member->phone }}</span></div>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach (App\Enums\Permission::cases() as $permission)
                        <label class="flex items-center gap-2 text-body-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" class="rounded border-outline-variant"
                                @checked(in_array($permission->value, $member->permissions ?? [], true))>
                            {{ $permission->label() }}
                        </label>
                    @endforeach
                </div>
                <div><x-button type="submit" variant="secondary">Simpan izin</x-button></div>
            </form>
        @empty
            <p class="text-on-surface-variant">Belum ada staf.</p>
        @endforelse
    </x-panel>

    <x-panel title="Undangan menunggu">
        @forelse ($invitations as $invitation)
            <div class="py-space-sm border-b border-outline-variant/40 last:border-0 flex items-center justify-between gap-3">
                <div>
                    <div class="font-label-lg">{{ $invitation->email ?? $invitation->phone }}</div>
                    <div class="font-body-sm text-secondary">Berlaku sampai {{ $invitation->expires_at->timezone(auth()->user()->tenant->timezone)->translatedFormat('j F Y H:i') }}</div>
                </div>
                <form method="POST" action="{{ route('owner.staff.invitations.revoke', $invitation) }}">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Cabut</x-button>
                </form>
            </div>
        @empty
            <p class="text-on-surface-variant">Tidak ada undangan yang menunggu.</p>
        @endforelse
    </x-panel>
@endsection
