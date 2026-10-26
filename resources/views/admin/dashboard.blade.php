@extends('layouts.internal')

@section('title', 'Dashboard Admin · ' . config('app.name'))
@section('heading', 'Dashboard Admin Dinas')
@section('subheading', 'Ringkasan kondisi data koperasi, antrian verifikasi, dan izin kedaluwarsa.')

@section('sidebar')
    <x-nav-link :active="true" :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :href="route('admin.akun.index')">Akun Pengelola</x-nav-link>
    <x-nav-link :href="route('admin.pengaturan.publik')">Pengaturan Publik</x-nav-link>
    <x-nav-link :href="route('admin.laporan.index')">Laporan</x-nav-link>
    <x-nav-link :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@section('content')
    @php
        // Tautan "Daftar" harus mempertahankan filter aktif lain agar angka tabel = hasil daftar.
        $activeFilters = array_filter([
            'region_id' => $filters['region_id'],
            'jenis_koperasi' => $filters['jenis_koperasi'],
            'status_verifikasi' => $filters['status_verifikasi'],
            'tahun' => $filters['tahun'],
        ], fn ($value): bool => $value !== '' && $value !== null);
    @endphp

    {{-- FR-020 "Data Masukan": filter wilayah/periode/status (konsisten dengan daftar koperasi). --}}
    <form method="GET" action="{{ route('admin.dashboard') }}" class="mb-3 flex flex-wrap items-end gap-3">
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
                <option value="">Semua status</option>
                @foreach ($statusLabels as $value => $label)
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

        @if ($hasFilters)
            <a href="{{ route('admin.dashboard') }}"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
        @endif
    </form>

    <p class="mb-4 text-xs text-slate-500">
        Filter di atas berlaku untuk <strong>statistik koperasi</strong> (FR-020). Antrian pengajuan (FR-021)
        dan izin kedaluwarsa (FR-050) memiliki cakupan sendiri. Periode = tahun
        <code>cooperatives.created_at</code> — PROVISIONAL sampai dinas menetapkan definisi resmi (Q-010).
    </p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Total Koperasi" :value="$totalCooperatives"
            :hint="$hasFilters ? 'Sesuai filter aktif' : 'Data aktif (tidak termasuk yang diarsipkan)'" />

        <x-stat-card label="Koperasi Disetujui" :value="$statusCounts['approved'] ?? 0"
            :hint="$hasFilters ? 'status_verifikasi = approved (sesuai filter)' : 'status_verifikasi = approved'" />

        <x-stat-card label="Antrian Pengajuan" :value="$queueCount" hint="Diajukan + Dalam Pemeriksaan">
            <x-slot:footer>
                <a href="{{ route('admin.pengajuan.index') }}" class="font-medium text-emerald-700 hover:underline">
                    Buka antrian
                </a>
            </x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Izin Kedaluwarsa" :value="$expiredLicenseCount" hint="Berdasarkan tanggal berlaku (FR-050)">
            <x-slot:footer>
                <a href="#izin-kedaluwarsa" class="font-medium text-emerald-700 hover:underline">Lihat daftar</a>
            </x-slot:footer>
        </x-stat-card>

        <x-stat-card label="Akan Kedaluwarsa" :value="$expiringLicenseCount"
            :hint="'Dalam '.$licenseExpiringDays.' hari (FR-022)'">
            <x-slot:footer>
                <a href="#izin-akan-kedaluwarsa" class="font-medium text-emerald-700 hover:underline">Lihat daftar</a>
            </x-slot:footer>
        </x-stat-card>
    </div>

    @if ($totalCooperatives === 0)
        @if ($hasFilters)
            <x-empty-state class="mt-6" title="Tidak ada data cocok filter"
                message="Tidak ada koperasi yang memenuhi filter wilayah/periode/status/jenis. Coba ubah atau reset filter.">
                <a href="{{ route('admin.dashboard') }}"
                    class="mt-3 inline-block rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Reset filter
                </a>
            </x-empty-state>
        @else
            <x-empty-state class="mt-6" title="Belum ada data koperasi"
                message="Statistik akan terisi setelah data koperasi ditambahkan. Tidak ada angka contoh yang ditampilkan.">
                <a href="{{ route('admin.koperasi.create') }}"
                    class="mt-3 inline-block rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Tambah Koperasi
                </a>
            </x-empty-state>
        @endif
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- FR-020: agregasi jumlah koperasi per status verifikasi. --}}
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Koperasi per Status Verifikasi
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($statusLabels as $status => $label)
                            <tr>
                                <td class="px-4 py-2 text-slate-700">{{ $label }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-900">{{ $statusCounts[$status] ?? 0 }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('admin.koperasi.index', array_merge($activeFilters, ['status_verifikasi' => $status])) }}"
                                        class="text-emerald-700 hover:underline">Daftar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- FR-020: agregasi per wilayah; region_id NULL = "Belum ditetapkan" (F6-4). --}}
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Koperasi per Wilayah
            </h2>

            @if (empty($regionCounts))
                <x-empty-state class="m-4" title="Belum ada sebaran wilayah"
                    message="Dataset wilayah resmi sudah tersedia, tetapi belum diimpor ke database (Q-012)." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100">
                            {{-- Bucket dikelompokkan per `region_id` (stabil), bukan per nama wilayah (R-4). --}}
                            @foreach ($regionCounts as $bucket)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ $bucket['label'] }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">{{ $bucket['total'] }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('admin.koperasi.index', array_merge($activeFilters, ['region_id' => $bucket['region_id'] ?? \App\Services\Reporting\CooperativeFilters::REGION_NONE])) }}"
                                            class="text-emerald-700 hover:underline">Daftar</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                    Dikelompokkan per <code>region_id</code> (kunci stabil), bukan per nama — wilayah yang
                    bernama sama ditampilkan sebagai baris terpisah agar angka bucket cocok dengan tautan
                    daftar. Koperasi tanpa wilayah ditampilkan sebagai "{{ $unassignedRegionLabel }}"
                    (Q-012: dataset tersedia, impor persisten belum dijalankan).
                </p>
            @endif
        </section>

        {{-- FR-020: agregasi per jenis koperasi. --}}
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Koperasi per Jenis
            </h2>

            @if (empty($jenisCounts))
                <x-empty-state class="m-4" title="Belum ada data jenis koperasi" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($jenisCounts as $jenis => $jumlah)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ $jenis }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">{{ $jumlah }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('admin.koperasi.index', array_merge($activeFilters, ['jenis_koperasi' => $jenis])) }}"
                                            class="text-emerald-700 hover:underline">Daftar</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- FR-020 "tren": periode memakai tahun created_at (definisi SEMENTARA F6-3). --}}
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Koperasi per Periode (Tahun Pendaftaran)
            </h2>

            @if (empty($periodCounts))
                <x-empty-state class="m-4" title="Belum ada data periode" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($periodCounts as $tahun => $jumlah)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ $tahun }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">{{ $jumlah }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('admin.koperasi.index', array_merge($activeFilters, ['tahun' => (string) $tahun])) }}"
                                            class="text-emerald-700 hover:underline">Daftar</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                PROVISIONAL — periode dihitung dari tahun <code>cooperatives.created_at</code> sampai
                definisi resmi ditetapkan dinas (Q-010). Tautan "Daftar" memakai filter tahun yang sama
                dan mempertahankan filter wilayah/jenis/status yang aktif.
            </p>
        </section>
    </div>

    {{-- FR-021: antrian pengajuan. Aksi mengikuti matriks transisi (PRD §12.2). --}}
    <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Antrian Pengajuan ({{ $queueCount }})
        </h2>

        @if ($queue->isEmpty())
            <x-empty-state class="m-4" title="Tidak ada pengajuan menunggu"
                message="Pengajuan yang diajukan pengelola akan muncul di sini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Koperasi</th>
                            <th class="px-4 py-3">Judul</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Menunggu sejak</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($queue as $submission)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $submission->cooperative?->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $submission->title }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$submission->status" /></td>
                                <td class="px-4 py-3 text-slate-600">{{ $submission->updated_at?->translatedFormat('d M Y, H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ($submission->status === 'submitted' && $submission->canTransitionTo('under_review'))
                                        <form method="POST" action="{{ route('admin.pengajuan.start-review', $submission) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-emerald-700 hover:underline">Mulai tinjau</button>
                                        </form>
                                    @elseif ($submission->status === 'under_review')
                                        <a href="{{ route('admin.pengajuan.show', $submission) }}" class="text-emerald-700 hover:underline">
                                            Tinjau &amp; putuskan
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.pengajuan.show', $submission) }}" class="ml-3 text-slate-600 hover:underline">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                Menampilkan maksimal 5 pengajuan terlama. Aksi "Mulai tinjau" hanya tersedia untuk status
                Diajukan; keputusan (setujui/perbaikan/tolak) dilakukan pada halaman detail sesuai matriks transisi.
            </p>
        @endif
    </section>

    {{-- FR-022: izin yang akan kedaluwarsa dalam ambang hari (PROVISIONAL, default 30). --}}
    <section id="izin-akan-kedaluwarsa" class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Izin Akan Kedaluwarsa ({{ $expiringLicenseCount }})
        </h2>

        @if ($expiringLicenses->isEmpty())
            <x-empty-state class="m-4" title="Tidak ada izin yang akan kedaluwarsa"
                message="Tidak ada izin yang berakhir dalam {{ $licenseExpiringDays }} hari ke depan." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Koperasi</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Nomor</th>
                            <th class="px-4 py-3">Berlaku s/d</th>
                            <th class="px-4 py-3">Sisa hari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($expiringLicenses as $license)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $license->cooperative?->nama ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $license->typeLabel() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $license->nomor }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $license->tanggal_berlaku_sampai?->translatedFormat('d F Y') ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $license->tanggal_berlaku_sampai ? max(0, (int) today()->diffInDays($license->tanggal_berlaku_sampai, false)) : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                Menampilkan maksimal 10 izin. Ambang hari bersifat PROVISIONAL dan dapat diubah lewat konfigurasi.
            </p>
        @endif
    </section>

    {{-- FR-050: izin yang SUDAH kedaluwarsa menurut tanggal. --}}
    <section id="izin-kedaluwarsa" class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Izin Kedaluwarsa ({{ $expiredLicenseCount }})
        </h2>

        @if ($expiredLicenses->isEmpty())
            <x-empty-state class="m-4" title="Tidak ada izin kedaluwarsa"
                message="Belum ada izin yang melewati tanggal berlaku." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Koperasi</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Nomor</th>
                            <th class="px-4 py-3">Berlaku s/d</th>
                            <th class="px-4 py-3">Status Izin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($expiredLicenses as $license)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $license->cooperative?->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $license->typeLabel() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $license->nomor }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $license->tanggal_berlaku_sampai?->translatedFormat('d F Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3"><x-license-status-badge :status="$license->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                Menampilkan maksimal 10 izin. Kadaluarsa diidentifikasi dari tanggal berlaku (FR-050).
                Peringatan izin mendekati kedaluwarsa ada di bagian atas (FR-022).
            </p>
        @endif
    </section>
@endsection
