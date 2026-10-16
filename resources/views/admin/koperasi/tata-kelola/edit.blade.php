@extends('layouts.internal')

@section('title', 'Ubah Tata Kelola · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Ubah Catatan Tata Kelola')
@section('subheading', $cooperative->nama . ' · ' . $record->period_year)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.koperasi.tata-kelola.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar tata kelola</a>
    </div>

    @include('admin.koperasi.tata-kelola._form', [
        'cooperative' => $cooperative,
        'record' => $record,
        'periodOptions' => $periodOptions,
        'statusOptions' => $statusOptions,
        'action' => route('admin.koperasi.tata-kelola.update', [$cooperative, $record]),
        'method' => 'PUT',
    ])
@endsection
