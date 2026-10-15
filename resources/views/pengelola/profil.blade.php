@extends('layouts.internal')

@section('title', 'Profil Koperasi · ' . config('app.name'))
@section('heading', 'Profil Koperasi')
@section('subheading', 'Data resmi koperasi Anda (hanya baca).')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">{{ $cooperative->nama }}</h2>
            <x-status-badge :status="$cooperative->status_verifikasi" />
        </div>

        <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Nomor identitas</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->nomor_identitas }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jenis</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->jenis_koperasi }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Bentuk</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->bentuk_koperasi ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal berdiri</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->tanggal_berdiri?->translatedFormat('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Kabupaten/Kota</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->region?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Telepon</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->telephone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Website</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->website ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Alamat</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $cooperative->alamat ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <x-alert type="info" class="mt-6">
        Pengajuan perubahan data oleh pengelola akan tersedia pada fase verifikasi (F5).
        Data resmi hanya berubah setelah disetujui admin dinas.
    </x-alert>
@endsection
