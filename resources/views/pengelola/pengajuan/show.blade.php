@extends('layouts.internal')

@section('title', 'Pengajuan #' . $submission->id . ' · ' . config('app.name'))
@section('heading', 'Detail Pengajuan')
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

@php
    $display = fn ($value) => ($value === null || $value === '') ? '—' : $value;
@endphp

@section('content')
    <div class="mb-4">
        <a href="{{ route('pengelola.pengajuan.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar pengajuan</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Status</p>
                <p class="mt-1"><x-status-badge :status="$submission->status" /></p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Diajukan</p>
                <p class="mt-1 text-sm text-slate-900">{{ $submission->created_at?->translatedFormat('d M Y, H:i') }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Peninjau</p>
                <p class="mt-1 text-sm text-slate-900">{{ $submission->reviewer?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Diterapkan</p>
                <p class="mt-1 text-sm text-slate-900">{{ $submission->applied_at?->translatedFormat('d M Y, H:i') ?? '—' }}</p>
            </div>
        </div>

        @if ($submission->reason)
            <div class="mt-4">
                <x-alert type="warning">
                    <strong>Catatan dari admin:</strong> {{ $submission->reason }}
                </x-alert>
            </div>
        @endif
    </div>

    @if ($submission->parent || $submission->children->isNotEmpty())
        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Rantai Versi Pengajuan</h2>

            @if ($submission->parent)
                <p class="mt-2 text-sm text-slate-700">
                    Pengajuan ini adalah <strong>versi perbaikan</strong> dari
                    <a href="{{ route('pengelola.pengajuan.show', $submission->parent) }}" class="font-medium text-emerald-700 hover:underline">
                        pengajuan #{{ $submission->parent->id }}</a>
                    <x-status-badge :status="$submission->parent->status" />
                </p>
            @endif

            @if ($submission->children->isNotEmpty())
                <p class="mt-2 text-sm text-slate-700">
                    Pengajuan ini <strong>sudah digantikan</strong> oleh versi perbaikan:
                </p>
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($submission->children as $child)
                        <li class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('pengelola.pengajuan.show', $child) }}" class="font-medium text-emerald-700 hover:underline">
                                Pengajuan #{{ $child->id }}</a>
                            <x-status-badge :status="$child->status" />
                            <span class="text-slate-500">{{ $child->created_at?->translatedFormat('d M Y, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-slate-500">
                    Versi ini tidak lagi dapat diperbaiki; gunakan versi terbaru di atas.
                </p>
            @endif
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Usulan Perubahan (nilai lama → baru)</h2>
        </div>

        @if ($submission->items->isEmpty())
            <x-empty-state title="Tidak ada item perubahan" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">Field</th>
                            <th class="px-5 py-3">Nilai lama</th>
                            <th class="px-5 py-3">Nilai baru</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($submission->items as $item)
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $fields[$item->field]['label'] ?? $item->field }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $display($item->old_value) }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $display($item->new_value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Aksi</h2>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            @can('update', $submission)
                <a href="{{ route('pengelola.pengajuan.edit', $submission) }}"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Ubah Draft
                </a>
            @endcan

            @can('submit', $submission)
                <form method="POST" action="{{ route('pengelola.pengajuan.submit', $submission) }}"
                    data-confirm="Kirim pengajuan ini untuk diverifikasi?">
                    @csrf
                    <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Kirim untuk Verifikasi
                    </button>
                </form>
            @endcan

            @can('archive', $submission)
                <form method="POST" action="{{ route('pengelola.pengajuan.archive', $submission) }}"
                    data-confirm="Arsipkan pengajuan ini?">
                    @csrf
                    <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                        Arsipkan
                    </button>
                </form>
            @endcan

            @can('revise', $submission)
                <a href="{{ route('pengelola.pengajuan.create', ['revision' => $submission->id]) }}"
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Buat Versi Perbaikan
                </a>
            @endcan

            @if ($submission->status === 'needs_revision' && $submission->isSuperseded())
                <p class="text-sm text-slate-500">
                    Sudah digantikan versi perbaikan yang lebih baru.
                </p>
            @elseif ($submission->status === 'submitted' || $submission->status === 'under_review')
                <p class="text-sm text-slate-500">Menunggu verifikasi admin dinas.</p>
            @endif
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Riwayat Status</h2>
        </div>

        @if ($submission->reviews->isEmpty())
            <x-empty-state title="Belum ada riwayat" />
        @else
            <ul class="divide-y divide-slate-200">
                @foreach ($submission->reviews->sortBy('created_at') as $review)
                    <li class="px-5 py-3 text-sm">
                        <span class="font-medium text-slate-900">{{ $review->toStatusLabel() }}</span>
                        <span class="text-slate-500">· {{ $review->created_at?->translatedFormat('d M Y, H:i') }}</span>
                        @if ($review->note)
                            <p class="mt-1 text-slate-600">{{ $review->note }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
