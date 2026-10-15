@extends('layouts.internal')

@section('title', $officer->name . ' · Pengurus · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', $officer->name)
@section('subheading', 'Detail pengurus koperasi.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.koperasi.pengurus.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar</a>
        @unless ($officer->trashed())
            <a href="{{ route('admin.koperasi.pengurus.edit', [$cooperative, $officer]) }}"
                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Ubah
            </a>
        @endunless
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Data Pengurus</h2>
            <div class="flex items-center gap-2">
                @if ($officer->is_active)
                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
                @else
                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Nonaktif</span>
                @endif
                @if ($officer->trashed())
                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Diarsipkan</span>
                @endif
            </div>
        </div>

        <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Nama</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $officer->name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jabatan</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $officer->position }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Nomor Identitas</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    @if ($officer->identity_number)
                        {{ $officer->maskedIdentityNumber() }}
                        <span class="ml-1 text-xs text-slate-500">(data pribadi, dimasking)</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Periode Jabatan</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ $officer->formattedPeriod() }}
                    @if ($officer->isCurrentlyServing())
                        <span class="ml-1 text-xs text-emerald-600">(masih menjabat)</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal Mulai</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $officer->period_start->translatedFormat('d F Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal Akhir</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    @if ($officer->period_end)
                        {{ $officer->period_end->translatedFormat('d F Y') }}
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>

        <p class="mt-5 border-t border-slate-100 pt-3 text-xs text-slate-500">
            @if ($officer->created_at)
                Ditambahkan {{ $officer->created_at->translatedFormat('d F Y, H:i') }}
            @endif
            @if ($officer->updated_at && !$officer->created_at->equalTo($officer->updated_at))
                · Terakhir diubah {{ $officer->updated_at->translatedFormat('d F Y, H:i') }}
            @endif
        </p>
    </div>

    @unless ($officer->trashed())
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50/50 p-5">
            <h2 class="text-sm font-semibold text-red-800">Arsipkan Pengurus</h2>
            <p class="mt-1 text-sm text-red-700">
                Data tidak dihapus permanen. Riwayat jabatan tetap tersimpan.
            </p>

            <form method="POST" action="{{ route('admin.koperasi.pengurus.destroy', [$cooperative, $officer]) }}" class="mt-4"
                onsubmit="return confirm('Arsipkan pengurus ini?');">
                @csrf
                @method('DELETE')

                <button type="submit"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Arsipkan Pengurus
                </button>
            </form>
        </div>
    @endunless
@endsection
