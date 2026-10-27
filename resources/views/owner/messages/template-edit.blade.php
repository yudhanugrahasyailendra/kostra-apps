@extends('layouts.owner')

@section('title', 'Ubah template: '.$key->label())

@section('content')
    <x-panel :title="'Ubah template: '.$key->label()">
        <form method="POST" action="{{ route('owner.message-templates.update', $key->value) }}" class="flex flex-col gap-space-md max-w-2xl">
            @csrf
            @method('PUT')

            <x-input name="name" label="Nama template" :value="$template?->name ?? $key->label()" maxlength="120" required />

            <div class="flex flex-col gap-1">
                <label for="body" class="font-label-md text-label-md text-on-surface">Isi pesan</label>
                <textarea id="body" name="body" rows="6" maxlength="1000" required
                          class="w-full rounded-lg bg-surface-container-low border border-outline-variant/60 px-3 py-2 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ old('body', $template?->body ?? $default) }}</textarea>
                <p class="font-body-sm text-body-sm text-secondary">
                    Variabel yang tersedia:
                    @foreach ($placeholders as $placeholder)
                        <code class="angka">{{ $placeholder }}</code>@if (! $loop->last), @endif
                    @endforeach
                </p>
                @error('body')
                    <p class="font-body-sm text-body-sm text-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 font-label-md">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template?->is_active ?? true)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                Template aktif (bila dimatikan, pesan jenis ini tidak dikirim)
            </label>

            <p class="font-body-sm text-secondary">Kategori selalu <strong>utility</strong>. Mengubah isi membuat salinan milik kos Anda; template bawaan sistem tidak berubah.</p>

            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.message-templates.index')" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>
@endsection
