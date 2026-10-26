@extends('layouts.public')

@section('title', $koperasi['nama'] ?? 'Profil Koperasi')

@section('content')
    <a href="{{ route('publik.direktori') }}" class="text-sm text-emerald-700 hover:underline">&larr; Kembali ke direktori</a>

    <article class="mt-4 rounded-lg border border-slate-200 bg-white p-6">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $koperasi['nama'] ?? '(nama tidak tersedia)' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $koperasi['jenis_koperasi'] ?? 'Jenis belum ditetapkan' }}</p>

        <dl class="mt-6 space-y-3 text-sm">
            @if (array_key_exists('nomor_identitas', $koperasi) && ! empty($koperasi['nomor_identitas']))
                <div class="flex flex-col sm:flex-row sm:gap-2">
                    <dt class="w-40 shrink-0 text-slate-500">Nomor Badan Hukum</dt>
                    <dd class="text-slate-800">{{ $koperasi['nomor_identitas'] }}</dd>
                </div>
            @endif

            @if (array_key_exists('tanggal_berdiri', $koperasi) && ! empty($koperasi['tanggal_berdiri']))
                <div class="flex flex-col sm:flex-row sm:gap-2">
                    <dt class="w-40 shrink-0 text-slate-500">Tanggal Berdiri</dt>
                    <dd class="text-slate-800">{{ $koperasi['tanggal_berdiri'] }}</dd>
                </div>
            @endif

            @if (array_key_exists('status_operasional', $koperasi) && ! empty($koperasi['status_operasional']))
                <div class="flex flex-col sm:flex-row sm:gap-2">
                    <dt class="w-40 shrink-0 text-slate-500">Status Operasional</dt>
                    <dd class="text-slate-800">{{ $koperasi['status_operasional'] }}</dd>
                </div>
            @endif

            @if (array_key_exists('wilayah', $koperasi) && ! empty($koperasi['wilayah']))
                <div class="flex flex-col sm:flex-row sm:gap-2">
                    <dt class="w-40 shrink-0 text-slate-500">Alamat</dt>
                    <dd class="text-slate-800">{{ $koperasi['wilayah'] }}</dd>
                </div>
            @endif

            @if (! empty($koperasi['email']))
                <div class="flex flex-col sm:flex-row sm:gap-2">
                    <dt class="w-40 shrink-0 text-slate-500">Email</dt>
                    <dd class="font-mono text-slate-800">{{ $koperasi['email'] }}</dd>
                </div>
            @endif

            @if (! empty($koperasi['telephone']))
                <div class="flex flex-col sm:flex-row sm:gap-2">
                    <dt class="w-40 shrink-0 text-slate-500">Telepon</dt>
                    <dd class="font-mono text-slate-800">{{ $koperasi['telephone'] }}</dd>
                </div>
            @endif

            @if (! empty($koperasi['website']))
                <div class="flex flex-col sm:flex-row sm:gap-2">
                    <dt class="w-40 shrink-0 text-slate-500">Situs web</dt>
                    <dd class="text-slate-800">
                        <a href="{{ $koperasi['website'] }}" class="text-emerald-700 hover:underline" rel="nofollow noopener" target="_blank">{{ $koperasi['website'] }}</a>
                    </dd>
                </div>
            @endif
        </dl>

        <p class="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-500">
            Hanya koperasi yang telah disetujui dan dipublikasikan yang ditampilkan. Sebagian data kontak disamarkan
            untuk melindungi data pribadi (NFR-SEC-015).
        </p>
    </article>
@endsection
