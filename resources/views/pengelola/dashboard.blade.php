@extends('layouts.internal')

@section('title', 'Dashboard Koperasi · ' . config('app.name'))
@section('heading', 'Dashboard Pengelola Koperasi')
@section('subheading', 'Status kelengkapan data dan pengajuan koperasi Anda.')

@section('sidebar')
    <x-nav-link :active="true" :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan &amp; Dokumen</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">Masuk sebagai</p>
            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $user->name }}</p>
            <p class="text-sm text-slate-500">{{ $user->email }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">Koperasi tertaut</p>
            <p class="mt-2">
                @if ($cooperative)
                    <x-badge color="blue">{{ $cooperative->nama }}</x-badge>
                @else
                    <x-badge color="amber">Belum tertaut</x-badge>
                @endif
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">Login terakhir</p>
            <p class="mt-1 text-sm font-medium text-slate-900">
                {{ $user->last_login_at?->translatedFormat('d F Y, H:i') ?? '—' }}
            </p>
        </div>
    </div>

    @if (! $cooperative)
        <x-alert type="warning" class="mt-6">
            Akun Anda belum ditautkan ke koperasi mana pun. Silakan hubungi admin dinas.
        </x-alert>
    @else
        <x-alert type="info" class="mt-6">
            Lihat <a href="{{ route('pengelola.profil') }}" class="font-medium underline">Profil Koperasi</a> untuk
            data resmi koperasi Anda, atau buat
            <a href="{{ route('pengelola.pengajuan.create') }}" class="font-medium underline">pengajuan perubahan</a>
            yang akan diverifikasi admin dinas.
        </x-alert>
    @endif
@endsection
