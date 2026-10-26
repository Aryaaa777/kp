@extends('layouts.internal')

@section('title', 'Aset · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', $asset->asset_type . ' · ' . $asset->period_year)
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.aset.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar</a>
        <a href="{{ route('admin.koperasi.aset.edit', [$cooperative, $asset]) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ubah</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tahun periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $asset->period_year }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jenis aset</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $asset->asset_type }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Nama aset</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $asset->asset_name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jumlah</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $asset->quantity ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Nilai (Rp)</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ number_format((float) $asset->value, 2, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal perolehan</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $asset->acquisition_date?->translatedFormat('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Kondisi</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $asset->conditionLabel() ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Keterangan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $asset->description ?: '—' }}</dd>
            </div>
        </dl>

        <p class="mt-5 border-t border-slate-100 pt-3 text-xs text-slate-500">
            Pencatat: {{ $asset->recorder?->name ?? '—' }}
            @if ($asset->created_at) · Dibuat {{ $asset->created_at->translatedFormat('d F Y, H:i') }} @endif
        </p>
    </div>

    <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
        PROVISIONAL — snapshot tahunan; bukan laporan keuangan resmi.
    </div>

    <div class="mt-6 rounded-lg border border-red-200 bg-red-50/50 p-5">
        <h2 class="text-sm font-semibold text-red-800">Arsipkan aset</h2>
        <p class="mt-1 text-sm text-red-700">Data tidak dihapus permanen (soft delete, BR-011). Data periode lain tidak terpengaruh.</p>
        <form method="POST" action="{{ route('admin.koperasi.aset.destroy', [$cooperative, $asset]) }}" class="mt-4"
            data-confirm="Arsipkan aset ini?">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Arsipkan</button>
        </form>
    </div>
@endsection
