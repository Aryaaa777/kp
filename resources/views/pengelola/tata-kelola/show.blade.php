@extends('layouts.internal')

@section('title', 'Tata Kelola ' . $record->period_year . ' · ' . config('app.name'))
@section('heading', 'Catatan Tata Kelola ' . $record->period_year)
@section('subheading', 'Data tata kelola koperasi Anda.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('pengelola.tata-kelola.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar</a>
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong> Periodisitas sementara tahunan; indikator tata
        kelola resmi belum ditetapkan sehingga nilai ini <em>bukan</em> penilaian resmi (Q-010).
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tahun periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->period_year }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ \App\Models\GovernanceRecord::periodOptions()[$record->period] ?? $record->period }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jumlah rapat</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->meeting_count ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Skor</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->governance_score ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->statusLabel() }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Ringkasan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $record->summary ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
        Hanya baca. Pengajuan perubahan oleh pengelola akan tersedia pada fase berikutnya.
    </div>
@endsection
