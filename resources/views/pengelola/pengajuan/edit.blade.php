@extends('layouts.internal')

@section('title', 'Ubah Pengajuan #' . $submission->id . ' · ' . config('app.name'))
@section('heading', 'Ubah Draft Pengajuan')
@section('subheading', $submission->title)

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('pengelola.pengajuan.show', $submission) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke detail pengajuan</a>
    </div>

    @if ($submission->reason)
        <div class="mb-4">
            <x-alert type="warning">
                <strong>Catatan perbaikan dari admin:</strong> {{ $submission->reason }}
            </x-alert>
        </div>
    @endif

    @include('pengelola.pengajuan._form', [
        'action' => route('pengelola.pengajuan.update', $submission),
        'method' => 'PUT',
        'submission' => $submission,
    ])
@endsection
