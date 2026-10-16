@extends('layouts.internal')

@section('title', 'Riwayat Anggota · ' . config('app.name'))
@section('heading', 'Detail Perubahan Keanggotaan')
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
    <div class="mb-4">
        <a href="{{ route('pengelola.anggota.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar</a>
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong> Pencatatan per kejadian; periodisitas dan
        definisi rekap resmi belum ditetapkan (Q-010). Tidak memuat data pribadi anggota.
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal kejadian</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $history->event_date?->translatedFormat('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jenis perubahan</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $history->changeTypeLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jumlah bertambah</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $history->members_added ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jumlah berkurang</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $history->members_removed ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Catatan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $history->notes ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
        Hanya baca. Pengajuan perubahan oleh pengelola akan tersedia pada fase berikutnya.
    </div>
@endsection
