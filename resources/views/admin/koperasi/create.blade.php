@extends('layouts.internal')

@section('title', 'Tambah Koperasi · ' . config('app.name'))
@section('heading', 'Tambah Koperasi')
@section('subheading', 'Masukkan data induk koperasi.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    @include('admin.koperasi._form', [
        'action' => route('admin.koperasi.store'),
        'method' => 'POST',
    ])
@endsection
