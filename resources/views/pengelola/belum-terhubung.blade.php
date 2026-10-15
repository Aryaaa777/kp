@extends('layouts.internal')

@section('title', 'Akun Belum Terhubung · ' . config('app.name'))
@section('heading', 'Akun Belum Terhubung')
@section('subheading', 'Akun Anda belum dikaitkan ke koperasi.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-6">
        <h2 class="text-sm font-semibold text-amber-900">Akun ini belum terhubung ke koperasi</h2>
        <p class="mt-2 text-sm text-amber-800">
            {{ $reason ?? 'Silakan hubungi admin dinas untuk mengaitkan akun Anda dengan data koperasi (BR-017).' }}
        </p>
    </div>
@endsection
