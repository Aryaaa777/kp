@extends('layouts.internal')

@section('title', 'Tata Kelola · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Data Tata Kelola')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.show', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke detail koperasi</a>
        <a href="{{ route('admin.koperasi.tata-kelola.create', $cooperative) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Tambah Catatan
        </a>
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong>
        Periodisitas untuk sementara <strong>tahunan</strong>. Indikator tata kelola resmi belum ditetapkan; nilai yang
        tersedia hanya contoh untuk simulasi dan bukan penilaian resmi (Q-010).
    </div>

    <form method="GET" action="{{ route('admin.koperasi.tata-kelola.index', $cooperative) }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="period_year" class="block text-xs font-medium text-slate-600">Filter tahun</label>
            <input type="number" id="period_year" name="period_year" value="{{ $yearFilter }}"
                class="mt-1 w-32 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        @if ($showArchived)
            <input type="hidden" name="arsip" value="1">
        @endif
        <button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Terapkan</button>
        @if ($yearFilter)
            <a href="{{ route('admin.koperasi.tata-kelola.index', array_filter(['cooperative' => $cooperative, 'arsip' => $showArchived ? 1 : null])) }}" class="text-sm text-slate-500 hover:underline">Reset</a>
        @endif
    </form>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        @if ($showArchived)
            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Menampilkan arsip</span>
            <a href="{{ route('admin.koperasi.tata-kelola.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">Kembali ke daftar aktif</a>
        @else
            <a href="{{ route('admin.koperasi.tata-kelola.index', ['cooperative' => $cooperative, 'arsip' => 1]) }}" class="text-sm text-slate-600 hover:underline">Lihat arsip</a>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($records->isEmpty())
            @if ($showArchived)
                <x-empty-state title="Tidak ada catatan terarsip"
                    message="Belum ada catatan tata kelola yang diarsipkan untuk koperasi ini." />
            @else
                <x-empty-state title="Belum ada catatan tata kelola"
                    message="Tambahkan catatan tata kelola untuk koperasi ini." />
            @endif
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
                                <td class="px-5 py-4 text-slate-700">{{ $periodOptions[$record->period] ?? $record->period }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $record->meeting_count ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $record->governance_score ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $record->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if ($showArchived)
                                        <form method="POST" action="{{ route('admin.koperasi.tata-kelola.restore', [$cooperative, $record]) }}"
                                            class="inline" onsubmit="return confirm('Pulihkan catatan tata kelola ini?');">
                                            @csrf
                                            <button type="submit" class="text-emerald-600 hover:underline">Pulihkan</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.koperasi.tata-kelola.show', [$cooperative, $record]) }}" class="text-blue-600 hover:underline">Detail</a>
                                        <a href="{{ route('admin.koperasi.tata-kelola.edit', [$cooperative, $record]) }}" class="ml-3 text-emerald-600 hover:underline">Ubah</a>
                                    @endif
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
