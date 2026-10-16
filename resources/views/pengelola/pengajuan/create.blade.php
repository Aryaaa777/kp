@extends('layouts.internal')

@section('title', 'Pengajuan Perubahan · ' . config('app.name'))
@section('heading', 'Pengajuan Perubahan Data')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('pengelola.pengajuan.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar pengajuan</a>
    </div>

    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong>
        Daftar field yang boleh diajukan pengelola masih menunggu konfirmasi (Q-006). Perubahan
        <strong>tidak</strong> langsung mengubah data resmi — menunggu persetujuan admin.
    </div>

    @include('pengelola.pengajuan._form', [
        'action' => route('pengelola.pengajuan.store'),
        'method' => 'POST',
        'submission' => null,
    ])
@endsection
