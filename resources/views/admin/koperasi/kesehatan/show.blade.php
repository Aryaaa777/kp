@extends('layouts.internal')

@section('title', 'Kesehatan ' . $assessment->period_year . ' · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Catatan Kesehatan ' . $assessment->period_year)
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.kesehatan.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar</a>
        <a href="{{ route('admin.koperasi.kesehatan.edit', [$cooperative, $assessment]) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ubah</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tahun periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->period_year }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ \App\Models\HealthAssessment::periodOptions()[$assessment->period] ?? $assessment->period }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal penilaian</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->assessment_date?->translatedFormat('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->statusLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Skor</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ $assessment->score ?? '—' }}
                    @if ($assessment->auto_calculated)
                        <span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-xs text-emerald-700">otomatis</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Predikat</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $assessment->predicate ?: '—' }}</dd>
            </div>
            @foreach (\App\Models\HealthAssessment::componentLabels() as $key => $label)
                @php($field = 'component_'.$key)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $assessment->getAttribute($field) ?? '—' }}</dd>
                </div>
            @endforeach
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Catatan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $assessment->notes ?: '—' }}</dd>
            </div>
        </dl>

        <p class="mt-5 border-t border-slate-100 pt-3 text-xs text-slate-500">
            Penilai: {{ $assessment->assessor?->name ?? '—' }}
            @if ($assessment->created_at) · Dibuat {{ $assessment->created_at->translatedFormat('d F Y, H:i') }} @endif
        </p>
    </div>

    <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
        PROVISIONAL — nilai/predikat ini bukan hasil penilaian resmi dinas.
    </div>

    <div class="mt-6 rounded-lg border border-red-200 bg-red-50/50 p-5">
        <h2 class="text-sm font-semibold text-red-800">Arsipkan catatan</h2>
        <p class="mt-1 text-sm text-red-700">Data tidak dihapus permanen (soft delete, BR-011).</p>
        <form method="POST" action="{{ route('admin.koperasi.kesehatan.destroy', [$cooperative, $assessment]) }}" class="mt-4"
            onsubmit="return confirm('Arsipkan catatan kesehatan ini?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Arsipkan</button>
        </form>
    </div>
@endsection
