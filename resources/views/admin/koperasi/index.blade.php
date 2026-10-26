@extends('layouts.internal')

@section('title', 'Data Koperasi · ' . config('app.name'))
@section('heading', 'Data Koperasi')
@section('subheading', 'Data induk koperasi Provinsi Jawa Barat.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :href="route('admin.laporan.index')">Laporan</x-nav-link>
    <x-nav-link :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.koperasi.index') }}" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
            <div>
                <label for="q" class="block text-xs font-medium text-slate-600">Cari</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Nama / nomor identitas"
                    class="mt-1 w-56 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="status_verifikasi" class="block text-xs font-medium text-slate-600">Status</label>
                <select id="status_verifikasi" name="status_verifikasi"
                    class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">Semua</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status_verifikasi'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="jenis_koperasi" class="block text-xs font-medium text-slate-600">Jenis</label>
                <select id="jenis_koperasi" name="jenis_koperasi"
                    class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">Semua</option>
                    @foreach ($jenisOptions as $jenis)
                        <option value="{{ $jenis }}" @selected($filters['jenis_koperasi'] === $jenis)>{{ $jenis }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="region_id" class="block text-xs font-medium text-slate-600">Wilayah</label>
                <select id="region_id" name="region_id"
                    class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">Semua wilayah</option>
                    <option value="{{ \App\Http\Controllers\Admin\CooperativeController::REGION_NONE }}"
                        @selected($filters['region_id'] === \App\Http\Controllers\Admin\CooperativeController::REGION_NONE)>
                        Belum ditetapkan
                    </option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected($filters['region_id'] === (string) $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="tahun" class="block text-xs font-medium text-slate-600">Tahun Periode</label>
                <select id="tahun" name="tahun"
                    class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">Semua tahun</option>
                    @foreach ($yearOptions as $tahun)
                        <option value="{{ $tahun }}" @selected($filters['tahun'] === (string) $tahun)>{{ $tahun }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Terapkan
            </button>

            @if ($filters['q'] !== '' || $filters['status_verifikasi'] !== '' || $filters['jenis_koperasi'] !== '' || $filters['region_id'] !== '' || $filters['tahun'] !== '')
                <a href="{{ route('admin.koperasi.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Reset
                </a>
            @endif
        </form>

        <a href="{{ route('admin.koperasi.create') }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Tambah Koperasi
        </a>
    </div>

    <p class="mb-3 text-xs text-slate-500">
        Filter tahun memakai tahun <code>cooperatives.created_at</code> — PROVISIONAL sampai definisi resmi
        periode ditetapkan dinas (Q-010).
    </p>

    @php
        $hasFilters = $filters['q'] !== ''
            || $filters['status_verifikasi'] !== ''
            || $filters['jenis_koperasi'] !== ''
            || $filters['region_id'] !== ''
            || $filters['tahun'] !== '';

        $sortLink = function (string $column) use ($filters) {
            $direction = ($filters['sort'] === $column && $filters['direction'] === 'asc') ? 'desc' : 'asc';

            return route('admin.koperasi.index', array_merge(request()->except('page'), ['sort' => $column, 'direction' => $direction]));
        };
    @endphp

    @if ($cooperatives->isEmpty())
        @if ($hasFilters)
            <x-empty-state title="Tidak ada data cocok filter"
                message="Tidak ada koperasi yang memenuhi kombinasi pencarian/filter. Coba ubah atau reset filter.">
                <a href="{{ route('admin.koperasi.index') }}"
                    class="mt-3 inline-block rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Reset filter
                </a>
            </x-empty-state>
        @else
            <x-empty-state title="Belum ada data koperasi"
                message="Data akan tampil setelah ditambahkan. Data yang telah diarsipkan tidak ditampilkan.">
                <a href="{{ route('admin.koperasi.create') }}"
                    class="mt-3 inline-block rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Tambah Koperasi
                </a>
            </x-empty-state>
        @endif
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3"><a href="{{ $sortLink('nama') }}" class="hover:text-slate-800">Nama</a></th>
                        <th class="px-4 py-3"><a href="{{ $sortLink('nomor_identitas') }}" class="hover:text-slate-800">Nomor Identitas</a></th>
                        <th class="px-4 py-3"><a href="{{ $sortLink('jenis_koperasi') }}" class="hover:text-slate-800">Jenis</a></th>
                        <th class="px-4 py-3">Wilayah</th>
                        <th class="px-4 py-3"><a href="{{ $sortLink('status_verifikasi') }}" class="hover:text-slate-800">Status</a></th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($cooperatives as $cooperative)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $cooperative->nama }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $cooperative->nomor_identitas }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $cooperative->jenis_koperasi }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $cooperative->region?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$cooperative->status_verifikasi" /></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('admin.koperasi.show', $cooperative) }}" class="text-emerald-700 hover:underline">Detail</a>
                                <a href="{{ route('admin.koperasi.edit', $cooperative) }}" class="ml-3 text-slate-600 hover:underline">Ubah</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $cooperatives->links() }}
        </div>
    @endif
@endsection
