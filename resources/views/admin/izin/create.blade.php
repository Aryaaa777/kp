@extends('layouts.internal')

@section('title', 'Tambah Izin · ' . config('app.name'))
@section('heading', 'Tambah Izin')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    @include('admin.izin._form', [
        'action' => route('admin.koperasi.izin.store', $cooperative),
        'method' => 'POST',
    ])
@endsection
