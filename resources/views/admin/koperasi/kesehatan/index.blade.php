@extends('layouts.internal')

@section('title', 'Kesehatan · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Penilaian Kesehatan')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.show', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke detail koperasi</a>
        <a href="{{ route('admin.koperasi.kesehatan.create', $cooperative) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Tambah Catatan
        </a>
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong>
        Periodisitas untuk sementara <strong>tahunan</strong>. Skor dan predikat diisi manual; <em>tidak ada formula resmi</em>
        dan tidak diklaim sebagai klasifikasi resmi. Aturan dapat berubah setelah konfirmasi dinas (Q-010/Q-007).
    </div>

    <form method="GET" action="{{ route('admin.koperasi.kesehatan.index', $cooperative) }}" class="mb-4 flex flex-wrap items-end gap-2">
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
            <a href="{{ route('admin.koperasi.kesehatan.index', array_filter(['cooperative' => $cooperative, 'arsip' => $showArchived ? 1 : null])) }}" class="text-sm text-slate-500 hover:underline">Reset</a>
        @endif
    </form>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        @if ($showArchived)
            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Menampilkan arsip</span>
            <a href="{{ route('admin.koperasi.kesehatan.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">Kembali ke daftar aktif</a>
        @else
            <a href="{{ route('admin.koperasi.kesehatan.index', ['cooperative' => $cooperative, 'arsip' => 1]) }}" class="text-sm text-slate-600 hover:underline">Lihat arsip</a>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($assessments->isEmpty())
            @if ($showArchived)
                <x-empty-state title="Tidak ada catatan terarsip"
                    message="Belum ada catatan kesehatan yang diarsipkan untuk koperasi ini." />
            @else
                <x-empty-state title="Belum ada catatan kesehatan"
                    message="Tambahkan catatan penilaian kesehatan untuk koperasi ini." />
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">Tahun</th>
                            <th class="px-5 py-3">Periode</th>
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Skor</th>
                            <th class="px-5 py-3">Predikat</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($assessments as $assessment)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-medium text-slate-900">{{ $assessment->period_year }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $periodOptions[$assessment->period] ?? $assessment->period }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $assessment->assessment_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $assessment->score ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $assessment->predicate ?: '—' }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $assessment->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if ($showArchived)
                                        <form method="POST" action="{{ route('admin.koperasi.kesehatan.restore', [$cooperative, $assessment]) }}"
                                            class="inline" onsubmit="return confirm('Pulihkan catatan kesehatan ini?');">
                                            @csrf
                                            <button type="submit" class="text-emerald-600 hover:underline">Pulihkan</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.koperasi.kesehatan.show', [$cooperative, $assessment]) }}" class="text-blue-600 hover:underline">Detail</a>
                                        <a href="{{ route('admin.koperasi.kesehatan.edit', [$cooperative, $assessment]) }}" class="ml-3 text-emerald-600 hover:underline">Ubah</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($assessments->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $assessments->links() }}</div>
            @endif
        @endif
    </div>
@endsection
