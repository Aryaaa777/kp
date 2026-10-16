@extends('layouts.internal')

@section('title', 'Audit Log · ' . config('app.name'))
@section('heading', 'Audit Log Aktivitas')
@section('subheading', 'Riwayat aksi sensitif (FR-151). Bersifat hanya-baca.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
        Log audit bersifat <strong>append-only</strong> — tidak dapat diubah atau dihapus dari antarmuka.
    </div>

    <form method="GET" action="{{ route('admin.audit-log.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="action" class="block text-xs font-medium text-slate-600">Aksi</label>
            <select id="action" name="action" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Semua aksi</option>
                @foreach ($actionOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $filters['action'] === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="user_id" class="block text-xs font-medium text-slate-600">Pengguna</label>
            <select id="user_id" name="user_id" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Semua pengguna</option>
                @foreach ($userOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $filters['user_id'] === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="q" class="block text-xs font-medium text-slate-600">Cari keterangan</label>
            <input id="q" name="q" type="text" value="{{ $filters['q'] }}"
                class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Filter
        </button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($logs->isEmpty())
            <x-empty-state title="Belum ada aktivitas" message="Belum ada log audit yang cocok dengan filter saat ini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">Waktu</th>
                            <th class="px-5 py-3">Aktor</th>
                            <th class="px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Target</th>
                            <th class="px-5 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3 text-slate-700">{{ $log->created_at?->translatedFormat('d M Y, H:i:s') }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $log->user?->name ?? 'Sistem' }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $log->action }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    @if ($log->subject_type)
                                        {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $log->description ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $logs->links() }}</div>
            @endif
        @endif
    </div>
@endsection
