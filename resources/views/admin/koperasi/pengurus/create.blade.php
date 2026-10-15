@extends('layouts.internal')

@section('title', 'Tambah Pengurus · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Tambah Pengurus')
@section('subheading', 'Tambahkan pengurus baru untuk ' . $cooperative->nama . '.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.koperasi.pengurus.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar pengurus</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <form method="POST" action="{{ route('admin.koperasi.pengurus.store', $cooperative) }}">
            @csrf

            <div class="space-y-5">
                <x-form.input
                    name="name"
                    label="Nama Pengurus"
                    required
                    :value="old('name')"
                    help="Nama lengkap pengurus koperasi."
                />

                <x-form.input
                    name="position"
                    label="Jabatan"
                    required
                    :value="old('position')"
                    help="Contoh: Ketua, Sekretaris, Bendahara, Pengawas."
                />

                <x-form.input
                    name="identity_number"
                    label="Nomor Identitas (opsional)"
                    :value="old('identity_number')"
                    help="NIK atau nomor identitas resmi (data pribadi, tidak dipublikasikan)."
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input
                        type="date"
                        name="period_start"
                        label="Tanggal Mulai Menjabat"
                        required
                        :value="old('period_start')"
                    />

                    <x-form.input
                        type="date"
                        name="period_end"
                        label="Tanggal Akhir Menjabat (opsional)"
                        :value="old('period_end')"
                        help="Kosongkan jika masih menjabat."
                    />
                </div>

                <x-form.checkbox
                    name="is_active"
                    label="Status Aktif"
                    :checked="old('is_active', true)"
                    help="Centang jika pengurus masih aktif."
                />
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Simpan
                </button>
                <a href="{{ route('admin.koperasi.pengurus.index', $cooperative) }}"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </form>
    </div>
@endsection
