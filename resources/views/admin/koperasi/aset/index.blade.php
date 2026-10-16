@extends('layouts.internal')

@section('title', 'Aset · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Aset Koperasi')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.show', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke detail koperasi</a>
        <a href="{{ route('admin.koperasi.aset.create', $cooperative) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Tambah Aset
        </a>
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong>
        Pencatatan aset untuk sementara berupa <strong>snapshot tahunan</strong>. Field jumlah, tanggal perolehan, dan
        kondisi belum ditetapkan resmi. Tidak ada integrasi akuntansi/perhitungan otomatis (Q-010).
    </div>

    <form method="GET" action="{{ route('admin.koperasi.aset.index', $cooperative) }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="period_year" class="block text-xs font-medium text-slate-600">Filter tahun</label>
            <input type="number" id="period_year" name="period_year" value="{{ $yearFilter }}"
                class="mt-1 w-32 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Terapkan</button>
        @if ($yearFilter)
            <a href="{{ route('admin.koperasi.aset.index', $cooperative) }}" class="text-sm text-slate-500 hover:underline">Reset</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($assets->isEmpty())
            <x-empty-state title="Belum ada aset tercatat"
                message="Tambahkan data aset untuk koperasi ini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">Tahun</th>
                            <th class="px-5 py-3">Jenis</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Jumlah</th>
                            <th class="px-5 py-3">Nilai (Rp)</th>
                            <th class="px-5 py-3">Kondisi</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($assets as $asset)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-medium text-slate-900">{{ $asset->period_year }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $asset->asset_type }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $asset->asset_name ?: '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $asset->quantity ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ number_format((float) $asset->value, 2, ',', '.') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $asset->conditionLabel() ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('admin.koperasi.aset.show', [$cooperative, $asset]) }}" class="text-blue-600 hover:underline">Detail</a>
                                    <a href="{{ route('admin.koperasi.aset.edit', [$cooperative, $asset]) }}" class="ml-3 text-emerald-600 hover:underline">Ubah</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($assets->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $assets->links() }}</div>
            @endif
        @endif
    </div>
@endsection
