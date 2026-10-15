@extends('layouts.internal')

@section('title', $license->typeLabel() . ' · ' . config('app.name'))
@section('heading', $license->typeLabel())
@section('subheading', 'Data izin koperasi Anda (hanya baca).')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.izin.index')">Perizinan &amp; Dokumen</x-nav-link>
@endsection

@section('content')
    <a href="{{ route('pengelola.izin.index') }}" class="text-sm text-slate-600 hover:underline">
        &larr; Kembali ke daftar izin
    </a>

    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Data izin</h2>
            <div class="flex items-center gap-2">
                @if ($license->isExpired())
                    <x-badge color="red">Melewati tanggal berlaku</x-badge>
                @endif
                <x-license-status-badge :status="$license->status" />
            </div>
        </div>

        <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jenis izin</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $license->typeLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Nomor izin</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $license->nomor }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal terbit</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $license->tanggal_terbit?->translatedFormat('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Berlaku sampai</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ $license->tanggal_berlaku_sampai?->translatedFormat('d F Y') ?? 'Tanpa batas' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Penerbit</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $license->penerbit ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Catatan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $license->catatan ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Dokumen pendukung</h2>

        @if ($license->documents->isEmpty())
            <p class="mt-2 text-sm text-slate-500">Belum ada dokumen pada izin ini.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($license->documents as $document)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div>
                            <p class="text-sm font-medium text-slate-900 break-all">{{ $document->original_name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $document->category }} · {{ $document->humanSize() }}
                                @if ($document->created_at) · {{ $document->created_at->translatedFormat('d M Y') }} @endif
                            </p>
                        </div>
                        <a href="{{ route('dokumen.unduh', $document) }}" class="text-sm text-emerald-700 hover:underline">Unduh</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
