@extends('layouts.internal')

@section('title', $officer->name . ' · Pengurus · ' . config('app.name'))
@section('heading', $officer->name)
@section('subheading', 'Detail pengurus koperasi.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('pengelola.pengurus.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar pengurus</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Data Pengurus</h2>
            @if ($officer->is_active)
                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
            @else
                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Nonaktif</span>
            @endif
        </div>

        <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Nama</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $officer->name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jabatan</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $officer->position }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Periode Jabatan</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ $officer->formattedPeriod() }}
                    @if ($officer->isCurrentlyServing())
                        <span class="ml-1 text-xs text-emerald-600">(masih menjabat)</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal Mulai</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $officer->period_start->translatedFormat('d F Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal Akhir</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    @if ($officer->period_end)
                        {{ $officer->period_end->translatedFormat('d F Y') }}
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>

        <div class="mt-5 rounded-md border border-blue-200 bg-blue-50 p-3">
            <p class="text-sm text-blue-800">
                Nomor identitas pengurus tidak ditampilkan untuk menjaga privasi (NFR-SEC-015).
            </p>
        </div>
    </div>
@endsection
