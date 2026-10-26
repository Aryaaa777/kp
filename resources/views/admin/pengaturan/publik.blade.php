@extends('layouts.internal')

@section('title', 'Pengaturan Publik · ' . config('app.name'))
@section('heading', 'Pengaturan Field Publik')
@section('subheading', 'Pilih data yang boleh tampil di direktori dan profil publik.')

@section('sidebar')
    <x-nav-link :href="route('admin.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('admin.koperasi.index')">Data Koperasi</x-nav-link>
    <x-nav-link :href="route('admin.akun.index')">Akun Pengelola</x-nav-link>
    <x-nav-link :active="true" :href="route('admin.pengaturan.publik')">Pengaturan Publik</x-nav-link>
    <x-nav-link :href="route('admin.laporan.index')">Laporan</x-nav-link>
    <x-nav-link :href="route('admin.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :href="route('admin.audit-log.index')">Audit Log</x-nav-link>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.pengaturan.publik.update') }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <x-alert type="danger">{{ $errors->first() }}</x-alert>
        @endif

        <x-alert type="info">
            Daftar di bawah adalah batas maksimum sesuai keputusan Q-008. Field di luar daftar ini — termasuk NIK,
            telepon pribadi pengurus, omzet spesifik, dan data personal anggota — tidak dapat dipilih dan tidak akan
            pernah dikirim ke halaman publik.
        </x-alert>

        <fieldset>
            <legend class="text-sm font-semibold text-slate-900">Field yang ditampilkan</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($allowedFields as $field)
                    <label class="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm">
                        <input type="checkbox" name="fields[]" value="{{ $field }}"
                            @checked(in_array($field, (array) old('fields', $settings['fields']), true))
                            class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>
                            <span class="font-medium text-slate-800">{{ $fieldLabels[$field] ?? $field }}</span>
                            <span class="block text-xs text-slate-500">{{ $field }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('fields')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('fields.*')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </fieldset>

        <fieldset class="space-y-3 border-t border-slate-100 pt-4">
            <legend class="text-sm font-semibold text-slate-900">Data tambahan</legend>

            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="show_region" value="1"
                    @checked((bool) old('show_region', $settings['show_region']))
                    class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>
                    <span class="font-medium text-slate-800">Alamat wilayah administratif</span>
                    <span class="block text-xs text-slate-500">
                        Disusun dari hierarki provinsi sampai desa/kelurahan. Teks alamat bebas tidak ditampilkan.
                    </span>
                </span>
            </label>

            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="contact_email" value="1"
                    @checked((bool) old('contact_email', $settings['contact']['email']))
                    class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>
                    <span class="font-medium text-slate-800">Email koperasi (disamarkan)</span>
                    <span class="block text-xs text-slate-500">Hanya bentuk termasking yang tampil.</span>
                </span>
            </label>

            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="contact_telephone" value="1"
                    @checked((bool) old('contact_telephone', $settings['contact']['telephone']))
                    class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>
                    <span class="font-medium text-slate-800">Telepon koperasi (disamarkan)</span>
                    <span class="block text-xs text-slate-500">Hanya bentuk termasking yang tampil.</span>
                </span>
            </label>
        </fieldset>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button type="submit"
                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Simpan Pengaturan
            </button>
            <a href="{{ route('publik.direktori') }}" target="_blank" rel="noopener"
                class="text-sm text-slate-600 hover:underline">Lihat direktori publik</a>
        </div>
    </form>
@endsection
