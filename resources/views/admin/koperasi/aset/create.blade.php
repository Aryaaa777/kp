@extends('layouts.internal')

@section('title', 'Tambah Aset · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Tambah Aset')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.koperasi.aset.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar aset</a>
    </div>

    @include('admin.koperasi.aset._form', [
        'cooperative' => $cooperative,
        'asset' => null,
        'typeOptions' => $typeOptions,
        'conditionOptions' => $conditionOptions,
        'action' => route('admin.koperasi.aset.store', $cooperative),
        'method' => 'POST',
    ])
@endsection
