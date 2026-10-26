@extends('layouts.public')

@section('title', 'Direktori Koperasi')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Direktori Koperasi</h1>
        <p class="mt-1 text-sm text-slate-600">
            Daftar koperasi yang telah disetujui dan dipublikasikan oleh Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat.
        </p>
    </div>

    <form method="GET" action="{{ route('publik.direktori') }}" class="mb-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="q" class="block text-xs font-medium text-slate-600">Cari nama koperasi</label>
            <input type="text" name="q" id="q" value="{{ $filters['q'] }}"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>

        @if (count($jenisOptions) > 0)
            <div>
                <label for="jenis" class="block text-xs font-medium text-slate-600">Jenis koperasi</label>
                <select name="jenis" id="jenis" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua jenis</option>
                    @foreach ($jenisOptions as $jenis)
                        <option value="{{ $jenis }}" @selected($filters['jenis'] === $jenis)>{{ $jenis }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if (count($regionOptions) > 0)
            <div>
                <label for="wilayah" class="block text-xs font-medium text-slate-600">Kabupaten/Kota</label>
                <select name="wilayah" id="wilayah" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua wilayah</option>
                    @foreach ($regionOptions as $region)
                        <option value="{{ $region['id'] }}" @selected($filters['wilayah'] === $region['id'])>{{ $region['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="flex items-end gap-2 sm:col-span-4">
            <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Terapkan</button>
            <a href="{{ route('publik.direktori') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </form>

    @if ($koperasi->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
            Tidak ada koperasi yang cocok dengan pencarian/filter Anda.
        </div>
    @else
        <p class="mb-3 text-xs text-slate-500">Menampilkan {{ $koperasi->count() }} dari {{ $koperasi->total() }} koperasi.</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($koperasi as $item)
                <article class="flex flex-col rounded-lg border border-slate-200 bg-white p-4">
                    <h2 class="text-base font-semibold text-slate-900">
                        <a href="{{ route('publik.koperasi', ['slug' => $item['slug']]) }}" class="hover:text-emerald-700">{{ $item['nama'] ?? '(nama tidak tersedia)' }}</a>
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">{{ $item['jenis_koperasi'] ?? 'Jenis belum ditetapkan' }}</p>
                    <dl class="mt-3 space-y-1 text-sm text-slate-700">
                        @if (array_key_exists('nomor_identitas', $item) && ! empty($item['nomor_identitas']))
                            <div class="flex gap-2">
                                <dt class="text-slate-500">Nomor Badan Hukum:</dt>
                                <dd>{{ $item['nomor_identitas'] }}</dd>
                            </div>
                        @endif
                        @if (array_key_exists('tanggal_berdiri', $item) && ! empty($item['tanggal_berdiri']))
                            <div class="flex gap-2">
                                <dt class="text-slate-500">Tanggal Berdiri:</dt>
                                <dd>{{ $item['tanggal_berdiri'] }}</dd>
                            </div>
                        @endif
                        @if (array_key_exists('status_operasional', $item) && ! empty($item['status_operasional']))
                            <div class="flex gap-2">
                                <dt class="text-slate-500">Status Operasional:</dt>
                                <dd>{{ $item['status_operasional'] }}</dd>
                            </div>
                        @endif
                        @if (array_key_exists('wilayah', $item) && ! empty($item['wilayah']))
                            <div class="flex gap-2">
                                <dt class="text-slate-500">Alamat:</dt>
                                <dd>{{ $item['wilayah'] }}</dd>
                            </div>
                        @endif
                        @if (! empty($item['email']))
                            <div class="flex gap-2">
                                <dt class="text-slate-500">Email:</dt>
                                <dd class="font-mono">{{ $item['email'] }}</dd>
                            </div>
                        @endif
                        @if (! empty($item['telephone']))
                            <div class="flex gap-2">
                                <dt class="text-slate-500">Telepon:</dt>
                                <dd class="font-mono">{{ $item['telephone'] }}</dd>
                            </div>
                        @endif
                        @if (! empty($item['website']))
                            <div class="flex gap-2">
                                <dt class="text-slate-500">Situs:</dt>
                                <dd><a href="{{ $item['website'] }}" class="text-emerald-700 hover:underline" rel="nofollow noopener" target="_blank">{{ $item['website'] }}</a></dd>
                            </div>
                        @endif
                    </dl>
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $koperasi->links() }}
        </div>
    @endif
@endsection
