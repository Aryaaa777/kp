@extends('layouts.app')

@section('title', 'Beranda · ' . config('app.name'))

@section('content')
    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-b from-[#f8f9ff] via-[#eff4ff] to-[#f8f9ff] px-4 py-12 sm:px-6 sm:py-20">
        <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-[#dbe1ff]/40 blur-3xl pointer-events-none"></div>
        <div class="absolute left-1/3 top-1/2 h-64 w-64 rounded-full bg-[#dce9ff]/50 blur-2xl pointer-events-none"></div>
        
        <div class="relative mx-auto max-w-4xl text-center">
            <div class="inline-flex items-center gap-2 rounded-xl bg-white px-3.5 py-1.5 shadow-xs border border-[#dce9ff] mb-6">
                <span class="material-symbols-outlined text-[20px] text-[#0051d5]">account_balance</span>
                <span class="text-xs font-bold uppercase tracking-wider text-[#001428]">Dinas Koperasi dan Usaha Kecil Prov. Jabar</span>
            </div>

            <h1 class="text-3xl font-extrabold tracking-tight text-[#001428] sm:text-5xl sm:leading-tight">
                Sistem Informasi Pendataan dan Pemantauan Koperasi
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-[#43474d] sm:text-lg">
                Platform terpusat untuk pendataan, pemutakhiran, verifikasi, dan pemantauan data koperasi
                di lingkungan Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat.
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('publik.direktori') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#001428] px-6 py-3 text-sm font-bold text-white shadow-md hover:bg-[#0f2942] transition-all">
                    <span class="material-symbols-outlined text-[20px] text-[#b0c9e8]">manage_search</span>
                    Lihat direktori koperasi
                </a>
                <a href="{{ route('login') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-[#001428] border border-[#c3c6ce] shadow-xs hover:bg-[#eff4ff] transition-all">
                    <span class="material-symbols-outlined text-[20px] text-[#0051d5]">login</span>
                    Masuk ke sistem
                </a>
            </div>

            <!-- Open Access Notice Banner -->
            <div class="mt-10 rounded-2xl border-l-4 border-[#0051d5] bg-white p-5 text-left shadow-xs flex flex-col sm:flex-row items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eff4ff] text-[#0051d5]">
                    <span class="material-symbols-outlined text-[22px]">info</span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#001428]">Informasi Akses Terbuka &amp; Tahapan Peluncuran</h3>
                    <p class="mt-1 text-xs sm:text-sm leading-relaxed text-[#43474d]">
                        Direktori dan profil koperasi yang telah dipublikasikan dapat diakses publik tanpa masuk.
                        Modul untuk Admin Dinas dan Pengelola Koperasi masih dalam penyempurnaan serta peninjauan
                        sebelum digunakan secara resmi.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- System Highlights Section -->
    <section class="bg-[#eff4ff] px-4 py-12 sm:px-6 sm:py-16">
        <div class="mx-auto max-w-6xl">
            <div class="mb-8">
                <span class="text-xs font-bold uppercase tracking-wider text-[#0051d5]">Fondasi &amp; Standarisasi</span>
                <h2 class="text-2xl font-bold tracking-tight text-[#001428] sm:text-3xl">Struktur Operasional Sistem Aktual</h2>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex flex-col justify-between rounded-2xl border border-[#dce9ff] bg-white p-6 shadow-xs">
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#eff4ff] text-[#001428]">
                                <span class="material-symbols-outlined text-[22px]">terminal</span>
                            </div>
                            <span class="rounded-full bg-[#eff4ff] px-2.5 py-0.5 text-xs font-semibold text-[#43474d]">Spesifikasi</span>
                        </div>
                        <h3 class="mt-4 text-base font-bold text-[#001428]">Teknologi</h3>
                        <dl class="mt-4 space-y-2.5 text-xs sm:text-sm">
                            <div class="flex items-center justify-between rounded-lg bg-[#f8f9ff] px-3 py-2 border border-[#dce9ff]/60">
                                <dt class="text-[#43474d]">Backend</dt>
                                <dd class="font-bold text-[#001428]">Laravel 13</dd>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-[#f8f9ff] px-3 py-2 border border-[#dce9ff]/60">
                                <dt class="text-[#43474d]">Tampilan</dt>
                                <dd class="font-bold text-[#001428]">Blade + Tailwind CSS</dd>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-[#f8f9ff] px-3 py-2 border border-[#dce9ff]/60">
                                <dt class="text-[#43474d]">Basis Data</dt>
                                <dd class="font-bold text-[#001428]">MariaDB / MySQL</dd>
                            </div>
                        </dl>
                    </div>
                    <p class="mt-4 text-xs text-[#74777e]">Dirancang untuk pemantauan data terstruktur dan terstandar.</p>
                </div>

                <div class="flex flex-col justify-between rounded-2xl border border-[#dce9ff] bg-white p-6 shadow-xs">
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#eff4ff] text-[#0051d5]">
                                <span class="material-symbols-outlined text-[22px]">groups</span>
                            </div>
                            <span class="rounded-full bg-[#eff4ff] px-2.5 py-0.5 text-xs font-semibold text-[#0051d5]">Otorisasi</span>
                        </div>
                        <h3 class="mt-4 text-base font-bold text-[#001428]">Kelompok Pengguna</h3>
                        <ul class="mt-4 space-y-2 text-xs sm:text-sm text-[#43474d]">
                            <li class="flex items-center gap-2.5 rounded-lg bg-[#f8f9ff] px-3 py-2 border border-[#dce9ff]/60">
                                <span class="material-symbols-outlined text-[18px] text-[#0051d5]">admin_panel_settings</span>
                                <div>
                                    <strong class="font-bold text-[#001428]">Admin Dinas:</strong> Verifikasi &amp; Pemantauan
                                </div>
                            </li>
                            <li class="flex items-center gap-2.5 rounded-lg bg-[#f8f9ff] px-3 py-2 border border-[#dce9ff]/60">
                                <span class="material-symbols-outlined text-[18px] text-[#0051d5]">store</span>
                                <div>
                                    <strong class="font-bold text-[#001428]">Pengelola:</strong> Pemutakhiran Profil Mandiri
                                </div>
                            </li>
                            <li class="flex items-center gap-2.5 rounded-lg bg-[#f8f9ff] px-3 py-2 border border-[#dce9ff]/60">
                                <span class="material-symbols-outlined text-[18px] text-[#0051d5]">public</span>
                                <div>
                                    <strong class="font-bold text-[#001428]">Masyarakat:</strong> Direktori &amp; Agregat Publik
                                </div>
                            </li>
                        </ul>
                    </div>
                    <p class="mt-4 text-xs text-[#74777e]">Peran akun terisolasi ketat sesuai prinsip anti-IDOR.</p>
                </div>

                <div class="flex flex-col justify-between rounded-2xl border border-[#dce9ff] bg-white p-6 shadow-xs sm:col-span-2 lg:col-span-1">
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#eff4ff] text-[#d77503]">
                                <span class="material-symbols-outlined text-[22px]">verified</span>
                            </div>
                            <span class="rounded-full bg-[#ffdcc3] px-2.5 py-0.5 text-xs font-semibold text-[#2f1500]">Kebijakan</span>
                        </div>
                        <h3 class="mt-4 text-base font-bold text-[#001428]">Tata Kelola Resmi</h3>
                        <p class="mt-4 text-xs sm:text-sm leading-relaxed text-[#43474d]">
                            Sistem mengintegrasikan seluruh tahapan registrasi, izin usaha, penilaian kesehatan,
                            serta rekam jejak audit log secara komprehensif berlandaskan regulasi dinas provinsi.
                        </p>
                    </div>
                    <div class="mt-4 rounded-lg bg-[#f8f9ff] p-3 text-xs text-[#43474d] border border-[#dce9ff]/60">
                        <strong class="text-[#001428]">Catatan Penting:</strong> Fitur berjalan bertahap mengacu pada keputusan resmi Dinas KUK Jabar.
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

