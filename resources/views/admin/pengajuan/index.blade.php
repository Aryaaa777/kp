@extends('layouts.internal')

@section('title', 'Antrian Pengajuan · ' . config('app.name'))
@section('heading', 'Antrian Pengajuan & Verifikasi')
@section('subheading', 'Tinjau pengajuan perubahan data dari pengelola koperasi.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong>
        Prosedur verifikasi untuk sementara <strong>satu tingkat</strong> oleh admin dinas (Q-004).
        Daftar modul &amp; field yang boleh diajukan pengelola menunggu konfirmasi (Q-006).
    </div>

    <form method="GET" action="{{ route('admin.pengajuan.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="block text-xs font-medium text-slate-600">Status</label>
            <select id="status" name="status" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Semua status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="q" class="block text-xs font-medium text-slate-600">Cari</label>
            <input id="q" name="q" type="text" value="{{ $filters['q'] }}" placeholder="Judul / nama / nomor koperasi"
                class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Filter
        </button>
        @if ($filters['status'] !== '' || $filters['q'] !== '')
            <a href="{{ route('admin.pengajuan.index') }}" class="text-sm text-slate-600 hover:underline">Reset</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($submissions->isEmpty())
            <x-empty-state title="Tidak ada pengajuan" message="Belum ada pengajuan yang cocok dengan filter saat ini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Koperasi</th>
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
                                <td class="px-5 py-4 font-medium text-slate-900">{{ $submission->cooperative?->nama ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $submission->title }}</td>
                                <td class="px-5 py-4"><x-status-badge :status="$submission->status" /></td>
                                <td class="px-5 py-4 text-slate-700">{{ $submission->updated_at?->translatedFormat('d M Y, H:i') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('admin.pengajuan.show', $submission) }}" class="text-blue-600 hover:underline">Tinjau</a>
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
