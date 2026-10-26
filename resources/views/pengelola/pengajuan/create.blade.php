@extends('layouts.internal')

@section('title', ($revision ? 'Versi Perbaikan Pengajuan #' . $revision->id : 'Pengajuan Perubahan') . ' · ' . config('app.name'))
@section('heading', $revision ? 'Buat Versi Perbaikan' : 'Pengajuan Perubahan Data')
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
        <a href="{{ $revision ? route('pengelola.pengajuan.show', $revision) : route('pengelola.pengajuan.index') }}" class="text-sm text-slate-600 hover:underline">&larr; {{ $revision ? 'Kembali ke pengajuan #' . $revision->id : 'Kembali ke daftar pengajuan' }}</a>
    </div>

    @if ($revision)
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <strong>Versi perbaikan dari pengajuan #{{ $revision->id }}.</strong>
            Perubahan akan disimpan sebagai <strong>pengajuan baru</strong> (versi perbaikan) yang
            tertaut ke pengajuan sebelumnya; versi lama tetap tersimpan sebagai riwayat.
            @if ($revision->reason)
                <p class="mt-2"><strong>Catatan perbaikan dari admin:</strong> {{ $revision->reason }}</p>
            @endif
        </div>
    @else
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <strong>PROVISIONAL — MENUNGGU KONFIRMASI DINAS.</strong>
            Daftar field yang boleh diajukan pengelola masih menunggu konfirmasi (Q-006). Perubahan
            <strong>tidak</strong> langsung mengubah data resmi — menunggu persetujuan admin.
        </div>
    @endif

    @include('pengelola.pengajuan._form', [
        'action' => route('pengelola.pengajuan.store'),
        'method' => 'POST',
        'submission' => null,
        'revision' => $revision,
    ])
@endsection
