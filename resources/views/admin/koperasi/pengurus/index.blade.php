@extends('layouts.internal')

@section('title', 'Pengurus · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', $cooperative->nama)
@section('subheading', 'Susunan pengurus koperasi.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.show', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke detail koperasi</a>
        <a href="{{ route('admin.koperasi.pengurus.create', $cooperative) }}"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            Tambah Pengurus
        </a>
    </div>

    @if (session('success'))
        <x-alert type="success" class="mb-4">
            {{ session('success') }}
        </x-alert>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Susunan Pengurus</h2>
            <p class="mt-1 text-sm text-slate-600">
                Daftar pengurus dan periode masa jabatan. Data tidak menimpa riwayat jabatan sebelumnya.
            </p>
        </div>

        @if ($officers->isEmpty())
            <x-empty-state
                title="Belum ada pengurus tercatat"
                description="Tambahkan pengurus pertama untuk koperasi ini."
                :action-url="route('admin.koperasi.pengurus.create', $cooperative)"
                action-label="Tambah Pengurus"
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                        <tr>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Jabatan</th>
                            <th class="px-5 py-3">Periode Jabatan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($officers as $officer)
                            <tr class="hover:bg-slate-50 {{ $officer->trashed() ? 'bg-slate-50 opacity-60' : '' }}">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-900">{{ $officer->name }}</div>
                                    @if ($officer->trashed())
                                        <div class="mt-0.5 text-xs text-red-600">Diarsipkan</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $officer->position }}</td>
                                <td class="px-5 py-4 text-slate-700">
                                    <div>{{ $officer->formattedPeriod() }}</div>
                                    @if ($officer->isCurrentlyServing())
                                        <div class="mt-0.5 text-xs text-emerald-600">Masih menjabat</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @if ($officer->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.koperasi.pengurus.show', [$cooperative, $officer]) }}"
                                            class="text-sm text-blue-600 hover:underline">Detail</a>
                                        @unless ($officer->trashed())
                                            <a href="{{ route('admin.koperasi.pengurus.edit', [$cooperative, $officer]) }}"
                                                class="text-sm text-emerald-600 hover:underline">Ubah</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($officers->hasPages())
                <div class="border-t border-slate-200 p-4">
                    {{ $officers->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
