@extends('layouts.internal')

@section('title', 'Pengurus · ' . config('app.name'))
@section('heading', 'Pengurus Koperasi')
@section('subheading', 'Susunan pengurus koperasi Anda.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Susunan Pengurus</h2>
            <p class="mt-1 text-sm text-slate-600">
                Daftar pengurus dan periode masa jabatan koperasi Anda.
            </p>
            <div class="mt-3 rounded-md border border-blue-200 bg-blue-50 p-3">
                <p class="text-sm text-blue-800">
                    <strong>Catatan:</strong> Anda hanya dapat melihat data pengurus. Untuk mengubah atau menambah pengurus, ajukan perubahan melalui workflow pengajuan (tersedia di F5).
                </p>
            </div>
        </div>

        @if ($officers->isEmpty())
            <x-empty-state
                title="Belum ada pengurus tercatat"
                description="Belum ada data pengurus untuk koperasi Anda. Hubungi admin dinas untuk menambahkan pengurus."
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Jabatan</th>
                            <th class="px-5 py-3">Periode Jabatan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($officers as $officer)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-900">{{ $officer->name }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $officer->position }}</td>
                                <td class="px-5 py-4 text-slate-700">
                                    <div>{{ $officer->formattedPeriod() }}</div>
                                    @if ($officer->isCurrentlyServing())
                                        <div class="mt-0.5 text-xs text-emerald-600">Masih menjabat</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @if ($officer->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('pengelola.pengurus.show', $officer) }}"
                                        class="text-sm text-blue-600 hover:underline">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($officers->hasPages())
                <div class="border-t border-slate-200 p-4">
                    {{ $officers->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
