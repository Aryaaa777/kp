@extends('layouts.internal')

@section('title', 'Ubah Pengurus · ' . $officer->name . ' · ' . $cooperative->nama . ' · ' . config('app.name'))
@section('heading', 'Ubah Pengurus')
@section('subheading', 'Perbarui data pengurus ' . $officer->name . '.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.koperasi.pengurus.index', $cooperative) }}" class="text-sm text-slate-600 hover:underline">&larr; Kembali ke daftar pengurus</a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <form method="POST" action="{{ route('admin.koperasi.pengurus.update', [$cooperative, $officer]) }}">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <x-form.input
                    name="name"
                    label="Nama Pengurus"
                    required
                    :value="old('name', $officer->name)"
                    help="Nama lengkap pengurus koperasi."
                />

                <x-form.input
                    name="position"
                    label="Jabatan"
                    required
                    :value="old('position', $officer->position)"
                    help="Contoh: Ketua, Sekretaris, Bendahara, Pengawas."
                />

                <x-form.input
                    name="identity_number"
                    label="Nomor Identitas (opsional)"
                    :value="old('identity_number')"
                    :placeholder="$officer->identity_number ? 'NIK tersimpan (kosongkan untuk mempertahankan)' : 'Masukkan NIK baru'"
                    help="NIK atau nomor identitas resmi (data pribadi, tidak dipublikasikan). Kosongkan jika tidak ingin mengubah."
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input
                        type="date"
                        name="period_start"
                        label="Tanggal Mulai Menjabat"
                        required
                        :value="old('period_start', $officer->period_start?->format('Y-m-d'))"
                    />

                    <x-form.input
                        type="date"
                        name="period_end"
                        label="Tanggal Akhir Menjabat (opsional)"
                        :value="old('period_end', $officer->period_end?->format('Y-m-d'))"
                        help="Kosongkan jika masih menjabat."
                    />
                </div>

                <x-form.checkbox
                    name="is_active"
                    label="Status Aktif"
                    :checked="old('is_active', $officer->is_active)"
                    help="Centang jika pengurus masih aktif."
                />
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Simpan Perubahan
                </button>
                <a href="{{ route('admin.koperasi.pengurus.index', $cooperative) }}"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </form>
    </div>
@endsection
