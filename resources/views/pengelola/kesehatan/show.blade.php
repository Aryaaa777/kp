@extends('layouts.internal')

@section('title', 'Kesehatan ' . $assessment->period_year . ' · ' . config('app.name'))
@section('heading', 'Catatan Kesehatan ' . $assessment->period_year)
@section('subheading', 'Penilaian kesehatan koperasi Anda.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('pengelola.kesehatan.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar</a>
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong> Periodisitas untuk sementara tahunan.
        Skor dan predikat adalah input manual dan <em>bukan</em> klasifikasi resmi dinas (Q-010/Q-007).
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tahun periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->period_year }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ \App\Models\HealthAssessment::periodOptions()[$assessment->period] ?? $assessment->period }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal penilaian</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->assessment_date?->translatedFormat('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->statusLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Skor</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->score ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Predikat</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->predicate ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Catatan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $assessment->notes ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
        Hanya baca (BR-010). Pengajuan perubahan oleh pengelola akan tersedia pada fase berikutnya.
    </div>
@endsection
