@extends('layouts.internal')

@section('title', 'Laporan Koperasi · ' . config('app.name'))
@section('heading', 'Laporan & Rekapitulasi Koperasi')
@section('subheading', 'Rekap koperasi terverifikasi per wilayah, jenis, dan periode.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.laporan.index')">Laporan</x-nav-link>
    <x-nav-link :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@section('content')
    <x-alert type="info" class="mb-4">
        <strong>Hanya data terverifikasi.</strong> Rekap ini hanya menghitung koperasi dengan status
        <strong>Disetujui</strong> (<code>{{ $verifiedStatus }}</code>) sesuai AC-REP-01. Status lain tidak pernah
        masuk agregasi — memilih status selain Disetujui menghasilkan rekap kosong.
    </x-alert>

    <form method="GET" action="{{ route('admin.laporan.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="region_id" class="block text-xs font-medium text-slate-600">Wilayah</label>
            <select id="region_id" name="region_id"
                class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Semua wilayah</option>
                <option value="{{ \App\Services\Reporting\CooperativeFilters::REGION_NONE }}"
                    @selected($filters['region_id'] === \App\Services\Reporting\CooperativeFilters::REGION_NONE)>
                    {{ $unassignedRegionLabel }}
                </option>
                @foreach ($regionOptions as $region)
                    <option value="{{ $region->id }}" @selected($filters['region_id'] === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="jenis_koperasi" class="block text-xs font-medium text-slate-600">Jenis</label>
            <select id="jenis_koperasi" name="jenis_koperasi"
                class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Semua jenis</option>
                @foreach ($jenisOptions as $jenis)
                    <option value="{{ $jenis }}" @selected($filters['jenis_koperasi'] === $jenis)>{{ $jenis }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status_verifikasi" class="block text-xs font-medium text-slate-600">Status</label>
            <select id="status_verifikasi" name="status_verifikasi"
                class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Semua (hanya Disetujui)</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status_verifikasi'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="tahun" class="block text-xs font-medium text-slate-600">Tahun Periode</label>
            <select id="tahun" name="tahun"
                class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Semua tahun</option>
                @foreach ($yearOptions as $tahun)
                    <option value="{{ $tahun }}" @selected($filters['tahun'] === (string) $tahun)>{{ $tahun }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Terapkan
        </button>

        @if (array_filter($filters) !== [])
            <a href="{{ route('admin.laporan.index') }}"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
        @endif

        <a href="{{ route('admin.laporan.export', array_filter($filters)) }}"
            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
            Ekspor CSV
        </a>
    </form>

    <p class="mb-4 text-xs text-slate-500">
        Periode memakai tahun <code>cooperatives.created_at</code> — PROVISIONAL sampai definisi resmi
        ditetapkan dinas (Q-010). Kebutuhan format laporan pimpinan (Q-022) belum ditetapkan, sehingga laporan ini
        dibatasi pada rekap agregat dasar. Ekspor CSV (FR-171) memakai filter yang sama dan hanya memuat
        koperasi terverifikasi.
    </p>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-card label="Koperasi Terverifikasi (sesuai filter)" :value="$total"
            hint="status_verifikasi = approved" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Rekap per Wilayah
            </h2>

            @if ($byRegion->isEmpty())
                <x-empty-state class="m-4" title="Tidak ada data"
                    message="Belum ada koperasi terverifikasi yang cocok dengan filter ini." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Wilayah</th>
                                <th class="px-4 py-3 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($byRegion as $row)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ $row->wilayah }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">{{ $row->total }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                    Koperasi tanpa wilayah masuk kategori "{{ $unassignedRegionLabel }}"
                    (Q-012: dataset tersedia, impor persisten belum dijalankan).
                </p>
            @endif
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Rekap per Jenis
            </h2>

            @if ($byJenis->isEmpty())
                <x-empty-state class="m-4" title="Tidak ada data"
                    message="Belum ada koperasi terverifikasi yang cocok dengan filter ini." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Jenis Koperasi</th>
                                <th class="px-4 py-3 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($byJenis as $row)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ $row->jenis }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">{{ $row->total }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Rekap per Periode
            </h2>

            @if ($byPeriod->isEmpty())
                <x-empty-state class="m-4" title="Tidak ada data"
                    message="Belum ada koperasi terverifikasi yang cocok dengan filter ini." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Tahun</th>
                                <th class="px-4 py-3 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($byPeriod as $row)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ $row->tahun }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">{{ $row->total }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                Periode = tahun <code>cooperatives.created_at</code> — PROVISIONAL sampai definisi resmi
                ditetapkan dinas (Q-010).
            </p>
        </section>
    </div>
@endsection
