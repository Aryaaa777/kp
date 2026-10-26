@extends('layouts.internal')

@section('title', 'Akun Pengelola · ' . config('app.name'))
@section('heading', 'Akun Pengelola')
@section('subheading', 'Kelola akun admin dinas dan pengelola koperasi. Password tidak pernah ditampilkan.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.akun.index')">Akun Pengelola</x-nav-link>
    <x-nav-link :href="route('admin.pengaturan.publik')">Pengaturan Publik</x-nav-link>
    <x-nav-link :href="route('admin.laporan.index')">Laporan</x-nav-link>
    <x-nav-link :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.akun.index') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="q" class="block text-xs font-medium text-slate-600">Cari</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Nama / email / koperasi"
                    class="mt-1 w-64 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="role" class="block text-xs font-medium text-slate-600">Peran</label>
                <select id="role" name="role"
                    class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option value="">Semua peran</option>
                    @foreach ($roleLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="block text-xs font-medium text-slate-600">Status</label>
                <select id="status" name="status"
                    class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option value="">Semua status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                </select>
            </div>

            <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Terapkan
            </button>

            @if ($filters['q'] !== '' || $filters['role'] !== '' || $filters['status'] !== '')
                <a href="{{ route('admin.akun.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Reset
                </a>
            @endif
        </form>

        <a href="{{ route('admin.akun.create') }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Tambah Akun
        </a>
    </div>

    <p class="mb-3 text-xs text-slate-500">
        Akun baru tidak memiliki password yang dapat dilihat admin. Sistem membuat password acak dan mengirim
        tautan reset ke email akun. Tautan reset juga dapat dikirim ulang dari tabel di bawah.
    </p>

    @if ($users->isEmpty())
        <x-empty-state title="Tidak ada akun"
            message="Belum ada akun yang cocok dengan pencarian/filter, atau akun belum dibuat.">
            <a href="{{ route('admin.akun.create') }}"
                class="mt-3 inline-block rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Tambah Akun
            </a>
        </x-empty-state>
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama / Email</th>
                        <th class="px-4 py-3">Peran</th>
                        <th class="px-4 py-3">Koperasi</th>
                        <th class="px-4 py-3">Wilayah</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Login Terakhir</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $account)
                        @php($roleName = $account->roles->first()?->name)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $account->name }}</div>
                                <div class="text-xs text-slate-500">{{ $account->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $roleName !== null ? ($roleLabels[$roleName] ?? $roleName) : '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $account->koperasi?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $account->region?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($account->status === 'active')
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $account->last_login_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('admin.akun.edit', $account) }}" class="text-emerald-700 hover:underline">Ubah</a>

                                @if (! auth()->user()->is($account))
                                    <form method="POST" action="{{ route('admin.akun.status', $account) }}" class="ml-3 inline">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $account->status === 'active' ? 'inactive' : 'active' }}">
                                        <button type="submit" class="text-slate-600 hover:underline">
                                            {{ $account->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.akun.reset', $account) }}" class="ml-3 inline">
                                    @csrf
                                    <button type="submit" class="text-slate-600 hover:underline">Kirim reset</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
@endsection
