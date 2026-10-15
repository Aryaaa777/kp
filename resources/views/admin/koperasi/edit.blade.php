@extends('layouts.internal')

@section('title', 'Ubah Koperasi · ' . config('app.name'))
@section('heading', 'Ubah Koperasi')
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    @include('admin.koperasi._form', [
        'action' => route('admin.koperasi.update', $cooperative),
        'method' => 'PUT',
    ])
@endsection
