@extends('layouts.internal')

@section('title', 'Riwayat Anggota · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Riwayat Keanggotaan')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.show', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke detail koperasi</a>
        <a href="{{ route('admin.koperasi.anggota.create', $cooperative) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Tambah Catatan
        </a>
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong>
        Riwayat dicatat <strong>per kejadian/perubahan</strong> (bukan rekap tahunan). Sistem <em>tidak</em> mengumpulkan
        data anggota individual/NIK anggota. Aturan dapat berubah setelah konfirmasi dinas (Q-010).
    </div>

    <form method="GET" action="{{ route('admin.koperasi.anggota.index', $cooperative) }}" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="change_type" class="block text-xs font-medium text-slate-600">Filter jenis perubahan</label>
            <select id="change_type" name="change_type"
                class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                <option value="">Semua</option>
                @foreach ($changeTypeOptions as $value => $label)
                    <option value="{{ $value }}" @selected($changeTypeFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Terapkan</button>
        @if ($changeTypeFilter !== '')
            <a href="{{ route('admin.koperasi.anggota.index', $cooperative) }}" class="text-sm text-slate-500 hover:underline">Reset</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($histories->isEmpty())
            <x-empty-state title="Belum ada riwayat keanggotaan"
                message="Tambahkan catatan perubahan keanggotaan untuk koperasi ini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Jenis</th>
                            <th class="px-5 py-3">Bertambah</th>
                            <th class="px-5 py-3">Berkurang</th>
                            <th class="px-5 py-3">Selisih</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($histories as $history)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-medium text-slate-900">{{ $history->event_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $history->changeTypeLabel() }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $history->members_added ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $history->members_removed ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    @php $net = $history->netChange(); @endphp
                                    <span class="font-medium {{ $net > 0 ? 'text-emerald-700' : ($net < 0 ? 'text-red-700' : 'text-slate-500') }}">
                                        {{ $net > 0 ? '+' : '' }}{{ $net }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('admin.koperasi.anggota.show', [$cooperative, $history]) }}" class="text-blue-600 hover:underline">Detail</a>
                                    <a href="{{ route('admin.koperasi.anggota.edit', [$cooperative, $history]) }}" class="ml-3 text-emerald-600 hover:underline">Ubah</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($histories->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $histories->links() }}</div>
            @endif
        @endif
    </div>
@endsection
