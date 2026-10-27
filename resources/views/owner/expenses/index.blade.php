@extends('layouts.owner')

@section('title', 'Pengeluaran')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-space-sm">
        <p class="text-secondary">Biaya operasional kos. Pengeluaran menjadi pengurang pemasukan pada rekap bulanan.</p>
        @can('create', App\Models\Expense::class)
            <x-button :href="route('owner.expenses.create')">Catat pengeluaran</x-button>
        @endcan
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
        <div class="panel p-space-md">
            <div class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">Total pengeluaran</div>
            <div class="font-currency-stat text-currency-stat angka">{{ App\Support\Rupiah::format($summary['total']) }}</div>
            <div class="font-body-sm text-secondary">{{ $summary['items'] }} catatan sesuai filter</div>
        </div>
    </div>

    <form method="GET" action="{{ route('owner.expenses.index') }}" class="panel p-space-md grid grid-cols-2 lg:grid-cols-5 gap-space-md items-end">
        <x-select name="period" label="Bulan">
            <option value="all" @selected($period === 'all')>Semua periode</option>
            @foreach ($months as $month)
                <option value="{{ $month->format('Y-m') }}" @selected($period === $month->format('Y-m'))>{{ $month->locale('id')->translatedFormat('F Y') }}</option>
            @endforeach
        </x-select>
        <x-select name="property_id" label="Properti" placeholder="Semua properti">
            @foreach ($properties as $property)
                <option value="{{ $property->id }}" @selected((int) $propertyId === $property->id)>{{ $property->name }}</option>
            @endforeach
        </x-select>
        <x-select name="expense_category_id" label="Kategori" placeholder="Semua kategori">
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) $categoryId === $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-select>
        <x-input name="q" label="Cari keterangan atau vendor" :value="$q" />
        <div class="flex gap-2">
            <x-button type="submit">Terapkan</x-button>
            <x-button :href="route('owner.expenses.index')" variant="secondary">Atur ulang</x-button>
        </div>
    </form>

    <x-panel>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Tanggal</th>
                        <th class="p-3">Keterangan</th>
                        <th class="p-3">Kategori</th>
                        <th class="p-3 hidden md:table-cell">Properti</th>
                        <th class="p-3 text-right">Jumlah</th>
                        <th class="p-3">Nota</th>
                        <th class="p-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr class="border-t border-outline-variant/40 hover:bg-surface-container-low/50">
                            <td class="p-3 angka">{{ $expense->spent_on->translatedFormat('j M Y') }}</td>
                            <td class="p-3">
                                {{ $expense->description }}
                                @if ($expense->vendor_name)
                                    <div class="font-body-sm text-secondary">{{ $expense->vendor_name }}</div>
                                @endif
                            </td>
                            <td class="p-3">{{ $expense->category->name }}</td>
                            <td class="p-3 hidden md:table-cell">{{ $expense->property?->name ?? 'Umum (semua properti)' }}</td>
                            <td class="p-3 angka text-right">{{ App\Support\Rupiah::format($expense->amount) }}</td>
                            <td class="p-3">
                                @forelse ($expense->attachments as $attachment)
                                    <a class="text-primary hover:underline" href="{{ $attachment->temporaryUrl() }}" target="_blank" rel="noopener">Lihat</a>
                                @empty
                                    <span class="text-secondary">Tidak ada</span>
                                @endforelse
                            </td>
                            <td class="p-3 text-right"><a class="text-primary hover:underline" href="{{ route('owner.expenses.edit', $expense) }}">Ubah</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4 text-on-surface-variant">Belum ada pengeluaran yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{ $expenses->links() }}
@endsection
