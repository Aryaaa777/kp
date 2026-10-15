@extends('layouts.internal')

@section('title', 'Dashboard Koperasi · ' . config('app.name'))
@section('heading', 'Dashboard Pengelola Koperasi')
@section('subheading', 'Status kelengkapan data dan pengajuan koperasi Anda.')

@section('sidebar')
    <x-nav-link :active="true" :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    @if ($user->koperasi_id)
        <x-nav-link :href="route('koperasi.show', ['koperasi' => $user->koperasi_id])">Ringkasan Koperasi</x-nav-link>
    @endif
    <span class="block rounded-md px-3 py-2 text-sm text-slate-400">Pengajuan (F5)</span>
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
                @if ($user->koperasi_id)
                    <x-badge color="blue">ID Koperasi: {{ $user->koperasi_id }}</x-badge>
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

    @if (! $user->koperasi_id)
        <x-alert type="warning" class="mt-6">
            Akun Anda belum ditautkan ke koperasi mana pun. Silakan hubungi admin dinas.
        </x-alert>
    @else
        <x-alert type="info" class="mt-6">
            Pengisian dan pengajuan data koperasi akan tersedia pada fase berikutnya.
        </x-alert>
    @endif
@endsection
