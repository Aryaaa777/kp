@extends('layouts.internal')

@section('title', 'Ringkasan Koperasi · ' . config('app.name'))
@section('heading', 'Ringkasan Koperasi')
@section('subheading', 'Halaman placeholder fondasi F1.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('koperasi.show', ['koperasi' => $koperasiId])">Ringkasan Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">ID koperasi yang Anda akses</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $koperasiId }}</p>
    </div>

    <x-alert type="info" class="mt-6">
        Halaman ini hanya membuktikan pembatasan akses antar koperasi (anti-IDOR). Data koperasi
        sesungguhnya belum ditampilkan dan akan dibangun pada fase F2.
    </x-alert>
@endsection
