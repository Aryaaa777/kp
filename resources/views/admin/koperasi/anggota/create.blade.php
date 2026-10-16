@extends('layouts.internal')

@section('title', 'Tambah Riwayat Anggota · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Tambah Riwayat Keanggotaan')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.koperasi.anggota.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke riwayat keanggotaan</a>
    </div>

    @include('admin.koperasi.anggota._form', [
        'cooperative' => $cooperative,
        'history' => null,
        'changeTypeOptions' => $changeTypeOptions,
        'action' => route('admin.koperasi.anggota.store', $cooperative),
        'method' => 'POST',
    ])
@endsection
