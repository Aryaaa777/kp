@extends('layouts.internal')

@section('title', 'Pengajuan Saya · ' . config('app.name'))
@section('heading', 'Pengajuan Perubahan Data')
@section('subheading', 'Riwayat pengajuan koperasi Anda.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('pengelola.pengajuan.index') }}" class="flex items-end gap-3">
            <div>
                <label for="status" class="block text-xs font-medium text-slate-600">Status</label>
                <select id="status" name="status" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Filter
            </button>
        </form>

        <a href="{{ route('pengelola.pengajuan.create') }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Buat Pengajuan
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($submissions->isEmpty())
            <x-empty-state title="Belum ada pengajuan"
                message="Anda belum membuat pengajuan perubahan data." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Judul</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Diperbarui</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($submissions as $submission)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 text-slate-500">{{ $submission->id }}</td>
                                <td class="px-5 py-4 font-medium text-slate-900">{{ $submission->title }}</td>
                                <td class="px-5 py-4"><x-status-badge :status="$submission->status" /></td>
                                <td class="px-5 py-4 text-slate-700">{{ $submission->updated_at?->translatedFormat('d M Y, H:i') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('pengelola.pengajuan.show', $submission) }}" class="text-blue-600 hover:underline">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($submissions->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $submissions->links() }}</div>
            @endif
        @endif
    </div>
@endsection
