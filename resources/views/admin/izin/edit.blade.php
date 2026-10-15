@extends('layouts.internal')

@section('title', 'Ubah Izin · ' . config('app.name'))
@section('heading', 'Ubah Izin')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    @include('admin.izin._form', [
        'action' => route('admin.koperasi.izin.update', [$cooperative, $license]),
        'method' => 'PUT',
    ])
@endsection
