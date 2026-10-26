@extends('layouts.internal')

@section('title', 'Pengajuan #' . $submission->id . ' · ' . config('app.name'))
@section('heading', 'Detail Pengajuan')
@section('subheading', $submission->cooperative?->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@php
    $display = fn ($value) => ($value === null || $value === '') ? '—' : $value;
@endphp

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.pengajuan.index') }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke antrian</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Koperasi</p>
                <p class="mt-1 font-medium text-slate-900">{{ $submission->cooperative?->nama ?? '—' }}</p>
                <p class="text-sm text-slate-500">{{ $submission->cooperative?->nomor_identitas }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Status</p>
                <p class="mt-1"><x-status-badge :status="$submission->status" /></p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Modul</p>
                <p class="mt-1 text-sm text-slate-900">{{ $submission->title }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Diajukan oleh</p>
                <p class="mt-1 text-sm text-slate-900">{{ $submission->submitter?->name ?? '—' }}</p>
                <p class="text-sm text-slate-500">{{ $submission->created_at?->translatedFormat('d M Y, H:i') }}</p>
            </div>
        </div>

        @if ($submission->reason)
            <div class="mt-4">
                <x-alert type="warning">
                    <strong>Alasan terakhir:</strong> {{ $submission->reason }}
                </x-alert>
            </div>
        @endif
    </div>

    @if ($submission->parent || $submission->children->isNotEmpty())
        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Rantai Versi Pengajuan</h2>

            @if ($submission->parent)
                <p class="mt-2 text-sm text-slate-700">
                    Versi perbaikan dari
                    <a href="{{ route('admin.pengajuan.show', $submission->parent) }}" class="font-medium text-emerald-700 hover:underline">
                        pengajuan #{{ $submission->parent->id }}</a>
                    <x-status-badge :status="$submission->parent->status" />
                </p>
            @endif

            @if ($submission->children->isNotEmpty())
                <p class="mt-2 text-sm text-slate-700">Digantikan oleh versi perbaikan:</p>
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($submission->children as $child)
                        <li class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.pengajuan.show', $child) }}" class="font-medium text-emerald-700 hover:underline">
                                Pengajuan #{{ $child->id }}</a>
                            <x-status-badge :status="$child->status" />
                            <span class="text-slate-500">{{ $child->created_at?->translatedFormat('d M Y, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-2 text-xs text-slate-500">
                Riwayat versi lama tetap tersimpan (usulan &amp; catatan tinjauannya tidak ditimpa).
            </p>
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
        <h2 class="text-sm font-semibold text-slate-900">Keputusan</h2>

        @if ($submission->status === 'submitted')
            <p class="mt-2 text-sm text-slate-600">Ambil pengajuan ini untuk ditinjau sebelum memberi keputusan.</p>
            <form method="POST" action="{{ route('admin.pengajuan.start-review', $submission) }}" class="mt-3">
                @csrf
                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Mulai Tinjau
                </button>
            </form>
        @elseif ($submission->status === 'under_review')
            <form method="POST" action="{{ route('admin.pengajuan.decide', $submission) }}" class="mt-3 space-y-4">
                @csrf
                <div>
                    <label for="decision" class="block text-sm font-medium text-slate-700">Keputusan</label>
                    <select id="decision" name="decision" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm sm:max-w-xs">
                        <option value="approve" @selected(old('decision') === 'approve')>Setujui &amp; terapkan</option>
                        <option value="needs_revision" @selected(old('decision') === 'needs_revision')>Perlu perbaikan</option>
                        <option value="rejected" @selected(old('decision') === 'rejected')>Tolak</option>
                    </select>
                    <x-form.error name="decision" />
                </div>

                <x-form.textarea name="reason" label="Alasan (wajib untuk perbaikan/penolakan)" :value="old('reason')"
                    help="Alasan wajib diisi saat meminta perbaikan atau menolak pengajuan." />

                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Simpan Keputusan
                </button>
            </form>
        @elseif (in_array($submission->status, ['approved', 'rejected'], true))
            <p class="mt-2 text-sm text-slate-600">Pengajuan telah selesai. Anda dapat mengarsipkannya.</p>
            <form method="POST" action="{{ route('admin.pengajuan.archive', $submission) }}" class="mt-3"
                onsubmit="return confirm('Arsipkan pengajuan ini?');">
                @csrf
                <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Arsipkan
                </button>
            </form>
        @else
            <p class="mt-2 text-sm text-slate-500">Tidak ada aksi tersedia untuk status saat ini.</p>
        @endif
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
                        <span class="text-slate-500">· {{ $review->user?->name ?? 'Sistem' }} · {{ $review->created_at?->translatedFormat('d M Y, H:i') }}</span>
                        @if ($review->note)
                            <p class="mt-1 text-slate-600">{{ $review->note }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
