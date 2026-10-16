@extends('layouts.internal')

@section('title', 'Aset · ' . config('app.name'))
@section('heading', 'Aset Koperasi')
@section('subheading', 'Aset koperasi Anda.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
        Hanya baca. Pengelola dapat melihat aset koperasinya; penulisan oleh pengelola tersedia pada fase berikutnya.
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong> Snapshot tahunan; bukan laporan keuangan resmi (Q-010).
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($assets->isEmpty())
            <x-empty-state title="Belum ada aset tercatat"
                message="Belum ada data aset untuk koperasi Anda." />
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
                                    <a href="{{ route('pengelola.aset.show', $asset) }}" class="text-blue-600 hover:underline">Detail</a>
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
