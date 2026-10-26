@extends('layouts.internal')

@section('title', $license->typeLabel() . ' · ' . config('app.name'))
@section('heading', $license->typeLabel())
@section('subheading', $cooperative->nama)

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.izin.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">
            &larr; Kembali ke daftar izin
        </a>
        <a href="{{ route('admin.koperasi.izin.edit', [$cooperative, $license]) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Ubah
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
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
        <h2 class="text-sm font-semibold text-slate-900">Verifikasi izin</h2>

        @if ($license->isVerified())
            <p class="mt-1 text-sm text-slate-600">
                Diverifikasi oleh <span class="font-medium">{{ $license->verifier?->name ?? '—' }}</span>
                @if ($license->verified_at) pada {{ $license->verified_at->translatedFormat('d F Y, H:i') }} @endif.
            </p>
        @else
            <p class="mt-1 text-sm text-slate-600">Belum diverifikasi.</p>
        @endif

        <form method="POST" action="{{ route('admin.koperasi.izin.verify', [$cooperative, $license]) }}" class="mt-4 space-y-4">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select name="status" label="Status verifikasi" required
                    :options="$verifyStatusOptions"
                    :selected="$license->isVerified() ? $license->status : 'active'"
                    help="Nilai status resmi belum dikonfirmasi dinas (Q-004)." />

                <div>
                    <x-form.textarea name="catatan" label="Catatan verifikasi" :value="$license->catatan"
                        help="Opsional." />
                </div>
            </div>

            <button type="submit"
                class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                Simpan Verifikasi
            </button>
        </form>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Dokumen pendukung</h2>

        @if ($license->documents->isEmpty())
            <p class="mt-2 text-sm text-slate-500">Belum ada dokumen diunggah.</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Berkas</th>
                            <th class="px-3 py-2">Kategori</th>
                            <th class="px-3 py-2">Versi</th>
                            <th class="px-3 py-2">Ukuran</th>
                            <th class="px-3 py-2">Diunggah</th>
                            <th class="px-3 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($license->documents as $document)
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-900 break-all">
                                    {{ $document->original_name }}
                                    @if ($document->isSuperseded())
                                        <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-500">Diarsipkan</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600">{{ $document->category }}</td>
                                <td class="px-3 py-2 text-slate-600">
                                    v{{ $document->version }}
                                    @if ($document->previous_version_id)
                                        <span class="block text-xs text-slate-400">dari v{{ $document->previousVersion?->version ?? $document->version - 1 }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-600">{{ $document->humanSize() }}</td>
                                <td class="px-3 py-2 text-slate-600">
                                    {{ $document->uploader?->name ?? '—' }}
                                    @if ($document->created_at)
                                        <span class="block text-xs text-slate-400">{{ $document->created_at->translatedFormat('d M Y, H:i') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    <a href="{{ route('dokumen.unduh', $document) }}" class="text-emerald-700 hover:underline">Unduh</a>

                                    @unless ($document->isSuperseded())
                                        <form method="POST"
                                            action="{{ route('admin.koperasi.izin.dokumen.versi', [$cooperative, $license, $document]) }}"
                                            enctype="multipart/form-data" class="mt-2 flex items-center justify-end gap-2">
                                            @csrf
                                            <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"
                                                class="w-40 text-xs text-slate-600">
                                            <button type="submit" class="text-emerald-700 hover:underline">Ganti versi</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.dokumen.destroy', $document) }}" class="mt-2 inline"
                                            data-confirm="Arsipkan dokumen ini dari daftar?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Arsipkan</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]) }}"
            enctype="multipart/form-data" class="mt-5 border-t border-slate-100 pt-4">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="category" label="Kategori dokumen" required
                    list="kategori-dokumen-saran"
                    help="Teks bebas. Contoh: akta_pendirian, izin_usaha, laporan_aset." />

                <x-form.file name="file" label="Berkas" required accept=".pdf,.jpg,.jpeg,.png"
                    help="Format: PDF, JPG, JPEG, PNG. Maksimal {{ round(config('koperasi.document_max_size_kb') / 1024) }} MB." />
            </div>

            <datalist id="kategori-dokumen-saran">
                @foreach ($categorySuggestions as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>

            <button type="submit"
                class="mt-3 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Unggah Dokumen
            </button>
        </form>
    </div>

    <div class="mt-6 rounded-lg border border-red-200 bg-red-50/50 p-5">
        <h2 class="text-sm font-semibold text-red-800">Arsipkan izin</h2>
        <p class="mt-1 text-sm text-red-700">
            Data tidak dihapus permanen. Izin yang diarsipkan tidak lagi muncul pada daftar aktif.
        </p>

        <form method="POST" action="{{ route('admin.koperasi.izin.destroy', [$cooperative, $license]) }}" class="mt-4"
            data-confirm="Arsipkan izin ini?">
            @csrf
            @method('DELETE')

            <button type="submit"
                class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                Arsipkan
            </button>
        </form>
    </div>
@endsection
