@extends('layouts.internal')

@section('title', 'Ubah Aset · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Ubah Aset')
@section('subheading', $cooperative->nama . ' · ' . $asset->period_year)

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
        'asset' => $asset,
        'typeOptions' => $typeOptions,
        'conditionOptions' => $conditionOptions,
        'action' => route('admin.koperasi.aset.update', [$cooperative, $asset]),
        'method' => 'PUT',
    ])
@endsection
