@extends('layouts.internal')

@section('title', $cooperative->nama . ' · ' . config('app.name'))
@section('heading', $cooperative->nama)
@section('subheading', 'Detail data induk koperasi.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar</a>
        <a href="{{ route('admin.koperasi.edit', $cooperative) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Ubah
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Data resmi</h2>
            <x-status-badge :status="$cooperative->status_verifikasi" />
        </div>

        <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Nomor identitas</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->nomor_identitas }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jenis</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->jenis_koperasi }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Bentuk</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->bentuk_koperasi ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal berdiri</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->tanggal_berdiri?->translatedFormat('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Kabupaten/Kota</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->region?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Telepon</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->telephone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Website</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->website ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Alamat</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $cooperative->alamat ?: '—' }}</dd>
            </div>
        </dl>

        <p class="mt-5 border-t border-slate-100 pt-3 text-xs text-slate-500">
            Dibuat oleh {{ $cooperative->creator?->name ?? '—' }}
            @if ($cooperative->created_at) · {{ $cooperative->created_at->translatedFormat('d F Y, H:i') }} @endif
            @if ($cooperative->updater) · Terakhir diubah oleh {{ $cooperative->updater->name }} @endif
        </p>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Perizinan &amp; Dokumen</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $cooperative->licenses_count }} izin tercatat.</p>
            </div>
            <a href="{{ route('admin.koperasi.izin.index', $cooperative) }}"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Kelola Perizinan
            </a>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-red-200 bg-red-50/50 p-5">
        <h2 class="text-sm font-semibold text-red-800">Arsipkan koperasi</h2>
        <p class="mt-1 text-sm text-red-700">
            Data tidak dihapus permanen. Data yang diarsipkan tidak lagi muncul pada daftar aktif.
        </p>

        <form method="POST" action="{{ route('admin.koperasi.destroy', $cooperative) }}" class="mt-4"
            onsubmit="return confirm('Arsipkan koperasi ini?');">
            @csrf
            @method('DELETE')

            <x-form.textarea name="archived_reason" label="Alasan pengarsipan" required rows="2"
                help="Wajib diisi (FR-043)." />

            <button type="submit"
                class="mt-3 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                Arsipkan
            </button>
        </form>
    </div>
@endsection
