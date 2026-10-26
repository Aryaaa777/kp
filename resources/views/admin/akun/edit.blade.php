@extends('layouts.internal')

@section('title', 'Ubah Akun · ' . config('app.name'))
@section('heading', 'Ubah Akun')
@section('subheading', $user->name)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.akun.index')">Akun Pengelola</x-nav-link>
    <x-nav-link :href="route('admin.pengaturan.publik')">Pengaturan Publik</x-nav-link>
    <x-nav-link :href="route('admin.laporan.index')">Laporan</x-nav-link>
    <x-nav-link :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@section('content')
    @include('admin.akun._form', [
        'action' => route('admin.akun.update', $user),
        'method' => 'PUT',
        'submitLabel' => 'Simpan Perubahan',
    ])
@endsection
