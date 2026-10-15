@extends('layouts.internal')

@section('title', 'Dashboard Admin · ' . config('app.name'))
@section('heading', 'Dashboard Admin Dinas')
@section('subheading', 'Ringkasan aktivitas dan antrian verifikasi.')

@section('sidebar')
    <x-nav-link :active="true" :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <span class="block rounded-md px-3 py-2 text-sm text-slate-400">Verifikasi (F5)</span>
@endsection

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">Masuk sebagai</p>
            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $user->name }}</p>
            <p class="text-sm text-slate-500">{{ $user->email }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">Peran</p>
            <p class="mt-2"><x-badge color="emerald">Admin Dinas</x-badge></p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">Login terakhir</p>
            <p class="mt-1 text-sm font-medium text-slate-900">
                {{ $user->last_login_at?->translatedFormat('d F Y, H:i') ?? '—' }}
            </p>
        </div>
    </div>

    <x-alert type="info" class="mt-6">
        Statistik dan antrian verifikasi belum tersedia. Modul koperasi, perizinan, dan verifikasi
        dibangun pada fase berikutnya. Tidak ada data contoh yang ditampilkan.
    </x-alert>
@endsection
