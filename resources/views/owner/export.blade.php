@extends('layouts.owner')

@section('title', 'Ekspor data')

@section('content')
    <x-panel title="Ekspor seluruh data kos">
        <p class="text-on-surface-variant mb-space-md">Unduh semua data kos Anda dalam satu berkas Excel, satu lembar per jenis data. Selalu tersedia, termasuk saat akun ditangguhkan atau langganan berakhir. Berkas memuat data pribadi penghuni: simpan dengan hati-hati.</p>

        <table class="w-full text-left mb-space-md">
            <thead>
                <tr><th>Lembar</th><th class="text-right">Baris</th></tr>
            </thead>
            <tbody>
                @foreach ($counts as $sheet => $count)
                    <tr><td>{{ $sheet }}</td><td class="angka text-right">{{ $count }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <p class="font-body-sm text-secondary mb-space-md">Tidak termasuk: foto KTP dan berkas lampiran. Unduhan tercatat di jejak audit.</p>
        <x-button :href="route('owner.export.download')">Unduh Excel</x-button>
    </x-panel>
@endsection
