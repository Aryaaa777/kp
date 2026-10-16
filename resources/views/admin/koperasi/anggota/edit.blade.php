@extends('layouts.internal')

@section('title', 'Ubah Riwayat Anggota · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Ubah Riwayat Keanggotaan')
@section('subheading', $cooperative->nama . ' · ' . $history->event_date?->format('d-m-Y'))

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
        'history' => $history,
        'changeTypeOptions' => $changeTypeOptions,
        'action' => route('admin.koperasi.anggota.update', [$cooperative, $history]),
        'method' => 'PUT',
    ])
@endsection
