@extends('layouts.app')

@section('title', 'Beranda · ' . config('app.name'))

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <div class="max-w-2xl">
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20">
                Fondasi Proyek — Fase F0
            </span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                Sistem Informasi Pendataan dan Pemantauan Koperasi
            </h1>
            <p class="mt-4 text-base leading-relaxed text-slate-600">
                Platform terpusat untuk pendataan, pemutakhiran, verifikasi, dan pemantauan data koperasi
                di lingkungan Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('login') }}"
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Masuk ke sistem
                </a>
            </div>

            <p class="mt-2 text-sm text-slate-500">
                Halaman ini merupakan fondasi teknis. Modul aplikasi akan dibangun pada fase berikutnya sesuai
                <span class="font-medium text-slate-700">docs/PRD.md</span> dan
                <span class="font-medium text-slate-700">docs/IMPLEMENTATION_PLAN.md</span>.
            </p>
        </div>

        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-slate-900">Status Fondasi</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-500">Backend</dt>
                        <dd class="font-medium text-slate-800">Laravel</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-500">UI</dt>
                        <dd class="font-medium text-slate-800">Blade + Tailwind CSS</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-500">Basis data</dt>
                        <dd class="font-medium text-slate-800">MariaDB / MySQL</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-slate-900">Kelompok Pengguna</h2>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <li>Admin Dinas</li>
                    <li>Pengelola Koperasi</li>
                    <li>Masyarakat (akses publik)</li>
                </ul>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 sm:col-span-2 lg:col-span-1">
                <h2 class="text-sm font-semibold text-slate-900">Catatan</h2>
                <p class="mt-3 text-sm text-slate-600">
                    Belum ada data koperasi nyata pada sistem. Seluruh modul bisnis belum diimplementasikan.
                </p>
            </div>
        </div>
    </section>
@endsection
