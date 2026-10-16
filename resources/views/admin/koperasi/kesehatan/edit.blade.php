@extends('layouts.internal')

@section('title', 'Ubah Catatan Kesehatan · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Ubah Catatan Kesehatan')
@section('subheading', $cooperative->nama . ' · ' . $assessment->period_year)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.koperasi.kesehatan.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar kesehatan</a>
    </div>

    @include('admin.koperasi.kesehatan._form', [
        'cooperative' => $cooperative,
        'assessment' => $assessment,
        'periodOptions' => $periodOptions,
        'statusOptions' => $statusOptions,
        'action' => route('admin.koperasi.kesehatan.update', [$cooperative, $assessment]),
        'method' => 'PUT',
    ])
@endsection
