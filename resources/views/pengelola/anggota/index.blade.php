@extends('layouts.internal')

@section('title', 'Riwayat Anggota · ' . config('app.name'))
@section('heading', 'Riwayat Keanggotaan')
@section('subheading', 'Riwayat keanggotaan koperasi Anda.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
        Hanya baca. Pengelola dapat melihat riwayat keanggotaan koperasinya; penulisan oleh pengelola tersedia pada fase berikutnya.
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong> Dicatat per kejadian/perubahan; bukan rekap tahunan (Q-010).
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($histories->isEmpty())
            <x-empty-state title="Belum ada riwayat keanggotaan"
                message="Belum ada catatan perubahan keanggotaan untuk koperasi Anda." />
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
                                <td class="px-5 py-4 text-slate-700">
                                    @php $net = $history->netChange(); @endphp
                                    {{ $net > 0 ? '+' : '' }}{{ $net }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('pengelola.anggota.show', $history) }}" class="text-blue-600 hover:underline">Detail</a>
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
