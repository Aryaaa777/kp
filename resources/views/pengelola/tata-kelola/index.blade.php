@extends('layouts.internal')

@section('title', 'Tata Kelola · ' . config('app.name'))
@section('heading', 'Data Tata Kelola Koperasi')
@section('subheading', 'Catatan tata kelola koperasi Anda.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
        Hanya baca. Pengelola dapat melihat data tata kelola koperasinya; penulisan oleh pengelola tersedia pada fase berikutnya.
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong> Periodisitas untuk sementara tahunan; indikator belum
        resmi (Q-010).
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($records->isEmpty())
            <x-empty-state title="Belum ada catatan tata kelola"
                message="Belum ada catatan tata kelola untuk koperasi Anda." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">Tahun</th>
                            <th class="px-5 py-3">Periode</th>
                            <th class="px-5 py-3">Jumlah Rapat</th>
                            <th class="px-5 py-3">Skor</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($records as $record)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-medium text-slate-900">{{ $record->period_year }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ \App\Models\GovernanceRecord::periodOptions()[$record->period] ?? $record->period }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $record->meeting_count ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $record->governance_score ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $record->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('pengelola.tata-kelola.show', $record) }}" class="text-blue-600 hover:underline">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $records->links() }}</div>
            @endif
        @endif
    </div>
@endsection
