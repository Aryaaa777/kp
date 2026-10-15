@extends('layouts.internal')

@section('title', 'Perizinan & Dokumen · ' . config('app.name'))
@section('heading', 'Perizinan & Dokumen')
@section('subheading', 'Izin koperasi Anda (hanya baca).')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.izin.index')">Perizinan &amp; Dokumen</x-nav-link>
@endsection

@section('content')
    @if ($licenses->isEmpty())
        <x-empty-state title="Belum ada izin tercatat"
            message="Izin usaha dan izin usaha simpan pinjam koperasi Anda belum tercatat." />
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Terbit</th>
                        <th class="px-4 py-3">Berlaku sampai</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Dokumen</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($licenses as $license)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $license->typeLabel() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $license->nomor }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $license->tanggal_terbit?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $license->tanggal_berlaku_sampai?->translatedFormat('d M Y') ?? 'Tanpa batas' }}
                                @if ($license->isExpired())
                                    <span class="ml-1 text-xs font-medium text-red-600">(lewat)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-license-status-badge :status="$license->status" /></td>
                            <td class="px-4 py-3 text-slate-600">{{ $license->documents_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('pengelola.izin.show', $license) }}" class="text-emerald-700 hover:underline">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-alert type="info" class="mt-6">
        Pengajuan penambahan/perubahan izin oleh pengelola akan tersedia pada fase verifikasi (F5).
        Data resmi hanya berubah setelah disetujui admin dinas.
    </x-alert>
@endsection
