@extends('layouts.owner')

@section('title', $expense ? 'Ubah pengeluaran' : 'Catat pengeluaran')

@section('content')
    <x-panel :title="$expense ? 'Ubah pengeluaran' : 'Catat pengeluaran'">
        <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-space-md max-w-2xl"
              action="{{ $expense ? route('owner.expenses.update', $expense) : route('owner.expenses.store') }}">
            @csrf
            @if ($expense)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <x-input name="spent_on" type="date" label="Tanggal" :value="$expense?->spent_on->toDateString() ?? $today" :max="$today" required />
                <x-input name="amount" label="Jumlah (rupiah)" inputmode="numeric" placeholder="250.000"
                         :value="$expense ? number_format($expense->amount, 0, ',', '.') : null" required />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <x-select name="expense_category_id" label="Kategori" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('expense_category_id', $expense?->expense_category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </x-select>
                <x-select name="property_id" label="Properti" placeholder="Umum (semua properti)">
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((int) old('property_id', $expense?->property_id) === $property->id)>{{ $property->name }}</option>
                    @endforeach
                </x-select>
            </div>

            <x-input name="description" label="Keterangan" :value="$expense?->description" maxlength="255" required />
            <x-input name="vendor_name" label="Penerima atau vendor (opsional)" :value="$expense?->vendor_name" maxlength="120" />

            <div>
                <x-input name="file" type="file" accept=".jpg,.jpeg,.png,.pdf" label="Nota (JPG, PNG, atau PDF, maksimal 5 MB)"
                         hint="Nota termasuk data keuangan: setelah diunggah tidak bisa dihapus." />
                @if ($expense && $expense->attachments->isNotEmpty())
                    <ul class="mt-space-sm font-body-sm">
                        @foreach ($expense->attachments as $attachment)
                            <li><a class="text-primary hover:underline" href="{{ $attachment->temporaryUrl() }}" target="_blank" rel="noopener">{{ $attachment->original_name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button :href="route('owner.expenses.index')" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-panel>

    @if ($expense)
        @can('delete', $expense)
            <x-panel title="Hapus dari laporan">
                <p class="text-body-md text-on-surface-variant mb-space-md">Pengeluaran ini keluar dari laporan dan rekap bulanan. Penghapusan tercatat di jejak audit.</p>
                <form method="POST" action="{{ route('owner.expenses.destroy', $expense) }}" onsubmit="return confirm('Hapus pengeluaran ini dari laporan?')">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Hapus pengeluaran</x-button>
                </form>
            </x-panel>
        @endcan
    @endif
@endsection
