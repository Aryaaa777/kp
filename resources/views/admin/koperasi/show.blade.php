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
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status operasional</dt>
                <dd class="mt-1 text-sm {{ $cooperative->statusOperasionalLabel() ? 'text-slate-900' : 'text-slate-500' }}">
                    {{ $cooperative->statusOperasionalLabel() ?? 'Belum ditetapkan' }}
                </dd>
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
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Wilayah</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ $cooperative->region?->fullPath() ?? '-' }}
                </dd>
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
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Publikasi direktori publik</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Menentukan apakah data koperasi ini tampil pada direktori publik (FR-190). Publikasi
                    bersifat opt-in: koperasi tampil hanya setelah diterbitkan (BR-016).
                </p>
            </div>
            <x-badge :color="$cooperative->is_published ? 'emerald' : 'slate'">
                {{ $cooperative->is_published ? 'Dipublikasikan' : 'Belum dipublikasikan' }}
            </x-badge>
        </div>

        @if ($cooperative->is_published)
            @if ($publicationBlocker !== null)
                <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Data masih ditandai <strong>Dipublikasikan</strong>, tetapi saat ini <strong>tidak tampil</strong>
                    di direktori publik. {{ $publicationBlocker }} Tarik publikasi atau perbaiki status verifikasi.
                </p>
            @endif

            <dl class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Dipublikasikan pada</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $cooperative->published_at?->translatedFormat('d F Y, H:i') ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Dipublikasikan oleh</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->publisher?->name ?? '—' }}</dd>
                </div>
            </dl>

            <form method="POST" action="{{ route('admin.koperasi.unpublish', $cooperative) }}" class="mt-4"
                onsubmit="return confirm('Tarik publikasi koperasi ini dari direktori publik?');">
                @csrf
                <button type="submit"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                    Tarik publikasi
                </button>
                <p class="mt-2 text-xs text-slate-500">
                    Status verifikasi tidak berubah dan data tidak diarsipkan.
                </p>
            </form>
        @elseif ($publicationBlocker !== null)
            <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ $publicationBlocker }}
            </p>
        @else
            <form method="POST" action="{{ route('admin.koperasi.publish', $cooperative) }}" class="mt-4"
                onsubmit="return confirm('Publikasikan koperasi ini ke direktori publik?');">
                @csrf
                <button type="submit"
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Publikasikan
                </button>
                @php
                    $statusPublikasi = \App\Models\Cooperative::publicStatusLabels();
                @endphp
                <p class="mt-2 text-xs text-slate-500">
                    @if ($statusPublikasi === [])
                        {{-- Defensif: bila daftar status publikasi kosong/berisi status tanpa label resmi,
                             tombol publikasi memang tidak muncul (lihat $publicationBlocker), namun teks
                             tetap dibuat aman dan tidak mengklaim koperasi ini pasti dapat dipublikasikan. --}}
                        Kelayakan publikasi mengikuti daftar status verifikasi yang ditetapkan dinas.
                    @else
                        Hanya koperasi dengan status verifikasi {{ implode(' atau ', $statusPublikasi) }} yang dapat dipublikasikan.
                    @endif
                </p>
            </form>
        @endif

        @if (! $cooperative->is_published && $cooperative->unpublished_at !== null)
            <dl class="mt-4 grid gap-x-6 gap-y-3 border-t border-slate-200 pt-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Penarikan terakhir</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $cooperative->unpublished_at->translatedFormat('d F Y, H:i') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Ditarik oleh</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $cooperative->unpublisher?->name ?? '-' }}</dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-slate-500">
                Jejak publikasi sebelumnya tidak dihapus dan tetap tercatat pada log audit.
            </p>
        @endif
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

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Susunan Pengurus</h2>
                <p class="mt-1 text-sm text-slate-600">Data pengurus koperasi dan periode jabatan.</p>
            </div>
            <a href="{{ route('admin.koperasi.pengurus.index', $cooperative) }}"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Kelola Pengurus
            </a>
        </div>
    </div>

    @php
        $periodikModules = [
            ['title' => 'Penilaian Kesehatan', 'desc' => 'Catatan penilaian kesehatan per periode (FR-080/081).', 'route' => 'admin.koperasi.kesehatan.index'],
            ['title' => 'Data Tata Kelola', 'desc' => 'Catatan tata kelola per periode (FR-090).', 'route' => 'admin.koperasi.tata-kelola.index'],
            ['title' => 'Aset Koperasi', 'desc' => 'Snapshot aset koperasi per periode (FR-100/101).', 'route' => 'admin.koperasi.aset.index'],
            ['title' => 'Riwayat Keanggotaan', 'desc' => 'Catatan perubahan keanggotaan (FR-110/111).', 'route' => 'admin.koperasi.anggota.index'],
        ];
    @endphp

    <div class="mt-6 grid gap-6 sm:grid-cols-2">
        @foreach ($periodikModules as $module)
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-slate-900">{{ $module['title'] }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $module['desc'] }}</p>
                <a href="{{ route($module['route'], $cooperative) }}"
                    class="mt-3 inline-block rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Kelola
                </a>
            </div>
        @endforeach
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
