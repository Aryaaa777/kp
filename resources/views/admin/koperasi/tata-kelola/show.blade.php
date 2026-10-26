@extends('layouts.internal')

@section('title', 'Tata Kelola ' . $record->period_year . ' · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Catatan Tata Kelola ' . $record->period_year)
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.tata-kelola.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar</a>
        <a href="{{ route('admin.koperasi.tata-kelola.edit', [$cooperative, $record]) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ubah</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tahun periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->period_year }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Periode</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ \App\Models\GovernanceRecord::periodOptions()[$record->period] ?? $record->period }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jumlah rapat</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->meeting_count ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Skor</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->governance_score ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->statusLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Kepatuhan (PROVISIONAL)</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    @if ($record->compliance_status)
                        <span class="rounded px-1.5 py-0.5 text-xs {{ $record->compliance_status === 'patuh' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                            {{ $record->complianceStatusLabel() }}
                        </span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">RAT tepat waktu</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->rat_on_time === null ? '—' : ($record->rat_on_time ? 'Ya' : 'Tidak') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Laporan keuangan tersedia</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->financial_report_available === null ? '—' : ($record->financial_report_available ? 'Ya' : 'Tidak') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Dokumen dasar lengkap</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $record->basic_documents_complete === null ? '—' : ($record->basic_documents_complete ? 'Ya' : 'Tidak') }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Catatan kepatuhan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $record->compliance_notes ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Ringkasan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $record->summary ?: '—' }}</dd>
            </div>
        </dl>

        <p class="mt-5 border-t border-slate-100 pt-3 text-xs text-slate-500">
            Pencatat: {{ $record->recorder?->name ?? '—' }}
            @if ($record->created_at) · Dibuat {{ $record->created_at->translatedFormat('d F Y, H:i') }} @endif
        </p>
    </div>

    <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
        PROVISIONAL — indikator/nilai ini bukan hasil evaluasi resmi dinas.
    </div>

    <div class="mt-6 rounded-lg border border-red-200 bg-red-50/50 p-5">
        <h2 class="text-sm font-semibold text-red-800">Arsipkan catatan</h2>
        <p class="mt-1 text-sm text-red-700">Data tidak dihapus permanen (soft delete, BR-011).</p>
        <form method="POST" action="{{ route('admin.koperasi.tata-kelola.destroy', [$cooperative, $record]) }}" class="mt-4"
            data-confirm="Arsipkan catatan tata kelola ini?">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Arsipkan</button>
        </form>
    </div>
@endsection
