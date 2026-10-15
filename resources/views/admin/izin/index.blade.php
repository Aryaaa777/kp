@extends('layouts.internal')

@section('title', 'Perizinan · ' . config('app.name'))
@section('heading', 'Perizinan & Dokumen')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.koperasi.show', $cooperative) }}" class="text-sm text-slate-600 hover:underline">
            &larr; Kembali ke detail koperasi
        </a>

        <a href="{{ route('admin.koperasi.izin.create', $cooperative) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Tambah Izin
        </a>
    </div>

    @if ($licenses->isEmpty())
        <x-empty-state title="Belum ada izin tercatat"
            message="Izin usaha dan izin usaha simpan pinjam koperasi ini belum ditambahkan.">
            <a href="{{ route('admin.koperasi.izin.create', $cooperative) }}"
                class="mt-3 inline-block rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Tambah Izin
            </a>
        </x-empty-state>
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
                                <a href="{{ route('admin.koperasi.izin.show', [$cooperative, $license]) }}"
                                    class="text-emerald-700 hover:underline">Detail</a>
                                <a href="{{ route('admin.koperasi.izin.edit', [$cooperative, $license]) }}"
                                    class="ml-3 text-slate-600 hover:underline">Ubah</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $licenses->links() }}
        </div>
    @endif
@endsection
