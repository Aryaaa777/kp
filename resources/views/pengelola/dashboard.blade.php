@extends('layouts.internal')

@section('title', 'Dashboard Koperasi · ' . config('app.name'))
@section('heading', 'Dashboard Pengelola Koperasi')
@section('subheading', 'Status kelengkapan data dan pengajuan koperasi Anda.')

@section('sidebar')
    <x-nav-link :active="true" :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan &amp; Dokumen</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    @if (! $cooperative)
        <x-alert type="warning">
            Akun Anda belum ditautkan ke koperasi mana pun, atau koperasi yang tertaut sudah diarsipkan.
            Silakan hubungi admin dinas. Indikator kelengkapan &amp; pengajuan belum dapat ditampilkan.
        </x-alert>
    @else
        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
            <span class="text-slate-500">Koperasi tertaut:</span>
            <x-badge color="blue">{{ $cooperative->nama }}</x-badge>
            <x-status-badge :status="$cooperative->status_verifikasi" />
            @if ($cooperative->region)
                <span class="text-slate-500">Wilayah: {{ $cooperative->region->name }}</span>
            @else
                <span class="text-slate-500">Wilayah: Belum ditetapkan</span>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Kelengkapan Profil" :value="$completeness['percentage'] . '%'"
                :hint="$completeness['filled'] . ' dari ' . $completeness['total'] . ' field profil terisi'" />

            <x-stat-card label="Field Wajib Pengelola"
                :value="$completeness['required_filled'] . '/' . $completeness['required_total']"
                hint="Field wajib dalam wewenang pengelola" />

            <x-stat-card label="Pengajuan Disetujui" :value="$submissionCounts['approved'] ?? 0"
                hint="Status pengajuan = Disetujui" />

            <x-stat-card label="Butuh Perbaikan" :value="$revisionCount"
                hint="Perlu Perbaikan (belum digantikan versi baru) / Ditolak" />
        </div>

        {{-- FR-030: kelengkapan field wewenang pengelola + informasi modul kewenangan admin. --}}
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-900">Kelengkapan Data Profil Koperasi</h2>
                <p class="text-sm text-slate-600">
                    <span class="font-semibold text-slate-900">{{ $completeness['percentage'] }}%</span>
                    ({{ $completeness['filled'] }}/{{ $completeness['total'] }} field wewenang pengelola)
                </p>
            </div>

            <div class="mt-3 h-2 w-full overflow-hidden rounded bg-slate-200" role="progressbar"
                aria-valuenow="{{ $completeness['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-2 rounded bg-emerald-600" style="width: {{ $completeness['percentage'] }}%"></div>
            </div>

            <ul class="mt-4 divide-y divide-slate-100">
                @foreach ($completeness['modules'] as $module)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div class="flex items-center gap-2">
                            <x-badge :color="$module['complete'] ? 'emerald' : 'slate'">
                                {{ $module['complete'] ? 'Lengkap' : 'Belum lengkap' }}
                            </x-badge>
                            <a href="{{ route($module['route']) }}" class="text-sm font-medium text-slate-800 hover:underline">
                                {{ $module['label'] }}
                            </a>
                        </div>
                        <span class="text-xs text-slate-500">{{ $module['detail'] }}</span>
                    </li>
                @endforeach
            </ul>

            <p class="mt-3 text-xs text-slate-500">
                Kelengkapan dihitung dari field yang dapat diisi/diusulkan pengelola
                ({{ $completeness['total'] }} field; {{ $completeness['required_total'] }} di antaranya wajib).
                Daftar field masih menunggu konfirmasi dinas (Q-006) sehingga rumusnya bersifat [ASUMSI].
            </p>

            <div class="mt-4 border-t border-slate-100 pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Data lain (dikelola admin dinas — informasi)
                </h3>
                <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($completeness['read_only'] as $module)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <a href="{{ route($module['route']) }}" class="font-medium text-slate-700 hover:underline">
                                {{ $module['label'] }}
                            </a>
                            <span class="text-xs text-slate-500">{{ $module['detail'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-slate-500">
                    Modul di atas berada di luar persentase kelengkapan pengelola karena diisi oleh admin dinas.
                </p>
            </div>
        </section>

        {{-- FR-031: pengajuan yang perlu diperbaiki beserta alasan & tautan. --}}
        <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Permintaan Perbaikan &amp; Penolakan
            </h2>

            @if ($revisionRequests->isEmpty())
                <x-empty-state class="m-4" title="Tidak ada permintaan perbaikan"
                    message="Pengajuan yang perlu diperbaiki atau ditolak akan tampil di sini." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Judul</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Alasan</th>
                                <th class="px-4 py-3">Diperbarui</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($revisionRequests as $submission)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $submission->title }}</td>
                                    <td class="px-4 py-3"><x-status-badge :status="$submission->status" /></td>
                                    <td class="px-4 py-3 text-slate-700">{{ $submission->reason ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $submission->updated_at?->translatedFormat('d M Y, H:i') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        @if ($submission->isRevisable())
                                            <a href="{{ route('pengelola.pengajuan.create', ['revision' => $submission->id]) }}"
                                                class="text-emerald-700 hover:underline">Perbaiki</a>
                                            <span class="text-slate-300">·</span>
                                        @elseif ($submission->status === 'needs_revision')
                                            <span class="text-slate-400">Sudah diperbaiki</span>
                                            <span class="text-slate-300">·</span>
                                        @endif
                                        <a href="{{ route('pengelola.pengajuan.show', $submission) }}"
                                            class="text-slate-600 hover:underline">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                    Perbaikan dibuat sebagai <strong>versi pengajuan baru</strong> yang tertaut ke pengajuan
                    sebelumnya (riwayat versi lama tersimpan).
                </p>
            @endif
        </section>

        {{-- FR-030: status pengajuan terkini koperasi milik akun (isolasi data). --}}
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Pengajuan Terbaru</h2>

            @if ($latestSubmission === null)
                <p class="mt-2 text-sm text-slate-600">
                    Belum ada pengajuan. Anda dapat
                    <a href="{{ route('pengelola.pengajuan.create') }}" class="font-medium text-emerald-700 hover:underline">
                        membuat pengajuan perubahan data</a>.
                </p>
            @else
                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                    <x-status-badge :status="$latestSubmission->status" />
                    <span class="font-medium text-slate-800">{{ $latestSubmission->title }}</span>
                    <span class="text-slate-500">
                        diperbarui {{ $latestSubmission->updated_at?->translatedFormat('d M Y, H:i') }}
                    </span>
                    <a href="{{ route('pengelola.pengajuan.show', $latestSubmission) }}"
                        class="text-emerald-700 hover:underline">Lihat detail</a>
                    <a href="{{ route('pengelola.pengajuan.index') }}" class="text-slate-600 hover:underline">
                        Semua pengajuan
                    </a>
                </div>
            @endif
        </section>
    @endif
@endsection
