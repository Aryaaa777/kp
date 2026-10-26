@extends('layouts.internal')

@section('title', 'Tambah Akun · ' . config('app.name'))
@section('heading', 'Tambah Akun')
@section('subheading', 'Buat akun admin dinas atau pengelola koperasi.')

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
        'action' => route('admin.akun.store'),
        'method' => 'POST',
        'submitLabel' => 'Buat Akun',
    ])
@endsection
