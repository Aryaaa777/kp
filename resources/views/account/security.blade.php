@extends('layouts.internal')

@section('title', 'Keamanan Akun · ' . config('app.name'))
@section('heading', 'Keamanan Akun')
@section('subheading', 'Batas waktu tidak aktif dan sesi yang sedang aktif.')

@section('sidebar')
    <x-nav-link :href="route('dashboard')">Dashboard</x-nav-link>
    @if (auth()->user()->hasRole('admin_dinas'))
        <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
        <x-nav-link :href="route('admin.akun.index')">Akun Pengelola</x-nav-link>
        <x-nav-link :href="route('admin.pengaturan.publik')">Pengaturan Publik</x-nav-link>
        <x-nav-link :href="route('admin.laporan.index')">Laporan</x-nav-link>
        <x-nav-link :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
        <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
    @endif
    <x-nav-link :active="true" :href="route('account.security')">Keamanan</x-nav-link>
@endsection

@section('content')
    <div class="space-y-5">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Batas waktu tidak aktif</h2>
            <p class="mt-2 text-sm text-slate-600">
                Sesi akan berakhir otomatis setelah <strong>{{ $idleTimeoutMinutes }} menit</strong> tanpa aktivitas.
                Setelah itu Anda perlu masuk kembali.
            </p>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Sesi saat ini</h2>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Alamat IP</dt>
                    <dd class="font-mono text-slate-800">{{ $currentIp ?? 'Tidak diketahui' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Perangkat / browser</dt>
                    <dd class="break-words text-slate-800">{{ $currentUserAgent ?: 'Tidak diketahui' }}</dd>
                </div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Sesi aktif</h2>
                <p class="mt-1 text-xs text-slate-500">
                    @if ($supportsSessionListing)
                        Daftar ini hanya mencakup sesi yang tercatat pada driver sesi database.
                    @else
                        Driver sesi saat ini tidak menyimpan metadata lintas perangkat, sehingga daftar sesi lain
                        tidak tersedia. Sesi saat ini tetap dapat diakhiri dengan tombol keluar.
                    @endif
                </p>
            </div>

            @if (! $supportsSessionListing)
                <div class="p-4">
                    <x-alert type="warning">
                        Daftar sesi lintas perangkat memerlukan <code>SESSION_DRIVER=database</code>. Pada konfigurasi
                        saat ini, pengakhiran sesi lain tidak dapat dilakukan dari halaman ini.
                    </x-alert>
                </div>
            @elseif ($sessions->isEmpty())
                <div class="p-4 text-sm text-slate-600">Belum ada sesi lain yang tercatat.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Perangkat / browser</th>
                                <th class="px-4 py-3">Alamat IP</th>
                                <th class="px-4 py-3">Aktivitas terakhir</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sessions as $session)
                                <tr>
                                    <td class="max-w-xs break-words px-4 py-3 text-slate-700">
                                        {{ \Illuminate\Support\Str::limit($session['user_agent'] ?: 'Tidak diketahui', 140) }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-slate-600">{{ $session['ip_address'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $session['last_activity']->translatedFormat('d M Y, H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($session['is_current'])
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Sesi ini</span>
                                        @else
                                            <span class="text-xs text-slate-500">Lain</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-slate-100 p-4">
                    <form method="POST" action="{{ route('account.security.sessions.destroy-others') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Akhiri semua sesi lain
                        </button>
                    </form>

                    <form method="POST" action="{{ route('account.security.sessions.destroy-all') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                            Keluar dari semua perangkat
                        </button>
                    </form>
                </div>
            @endif
        </section>
    </div>
@endsection
