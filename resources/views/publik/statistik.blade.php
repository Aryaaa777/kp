@extends('layouts.public')

@section('title', 'Statistik Koperasi')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Statistik Koperasi</h1>
        <p class="mt-1 text-sm text-slate-600">
            Ringkasan agregat koperasi yang telah disetujui dan dipublikasikan.
            Data per koperasi tidak ditampilkan di halaman ini.
        </p>
        <a href="{{ route('publik.direktori') }}" class="mt-3 inline-block text-sm font-medium text-emerald-700 hover:underline">
            Lihat direktori koperasi &rarr;
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total koperasi terpublikasi</p>
        <p class="mt-1 text-3xl font-semibold text-slate-900">{{ number_format($total, 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-slate-500">
            Hanya koperasi berstatus resmi dan dipublikasikan yang dihitung.
        </p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Per Kabupaten/Kota
            </h2>

            @if (count($byRegion) === 0)
                <p class="p-4 text-sm text-slate-500">Belum ada data wilayah.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Wilayah</th>
                            <th class="px-4 py-2 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($byRegion as $row)
                            <tr>
                                <td class="px-4 py-2 text-slate-700">{{ $row['label'] }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-900">{{ number_format($row['total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Per Jenis Koperasi
            </h2>

            @if (count($byJenis) === 0)
                <p class="p-4 text-sm text-slate-500">Belum ada data jenis koperasi.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Jenis</th>
                            <th class="px-4 py-2 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($byJenis as $row)
                            <tr>
                                <td class="px-4 py-2 text-slate-700">{{ $row['label'] }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-900">{{ number_format($row['total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Per Status Operasional
        </h2>

        @if (count($byStatus) === 0)
            <p class="p-4 text-sm text-slate-500">Belum ada data status operasional.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($byStatus as $row)
                        <tr>
                            <td class="px-4 py-2 text-slate-700">{{ $row['label'] }}</td>
                            <td class="px-4 py-2 text-right font-medium text-slate-900">{{ number_format($row['total'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <p class="mt-6 text-xs text-slate-500">
        PROVISIONAL: cakupan statistik mengikuti kebijakan publikasi yang berlaku dan dapat berubah
        setelah dinas menetapkan aturan resmi.
    </p>
@endsection
