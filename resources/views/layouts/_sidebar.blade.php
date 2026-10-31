@php
    $routeName = request()->route()?->getName() ?? '';
    $user = auth()->user();
@endphp

@if ($user?->hasRole('admin_dinas'))
    <x-nav-link
        :active="str_starts_with($routeName, 'admin.dashboard')"
        :href="route('admin.dashboard')">
        Dashboard
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.koperasi.')"
        :href="route('admin.koperasi.index')">
        Data Koperasi
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.akun.')"
        :href="route('admin.akun.index')">
        Akun Pengelola
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.pengaturan.')"
        :href="route('admin.pengaturan.publik')">
        Pengaturan Publik
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.laporan.')"
        :href="route('admin.laporan.index')">
        Laporan
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.pengajuan.')"
        :href="route('admin.pengajuan.index')">
        Pengajuan
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.audit-log.')"
        :href="route('admin.audit-log.index')">
        Audit Log
    </x-nav-link>
@elseif ($user?->hasRole('pengelola_koperasi'))
    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.dashboard')"
        :href="route('pengelola.dashboard')">
        Dashboard
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.profil')"
        :href="route('pengelola.profil')">
        Profil Koperasi
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.izin.')"
        :href="route('pengelola.izin.index')">
        Perizinan &amp; Dokumen
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.pengurus.')"
        :href="route('pengelola.pengurus.index')">
        Pengurus
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.kesehatan.')"
        :href="route('pengelola.kesehatan.index')">
        Kesehatan
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.tata-kelola.')"
        :href="route('pengelola.tata-kelola.index')">
        Tata Kelola
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.aset.')"
        :href="route('pengelola.aset.index')">
        Aset
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.anggota.')"
        :href="route('pengelola.anggota.index')">
        Anggota
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.pengajuan.')"
        :href="route('pengelola.pengajuan.index')">
        Pengajuan
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.notifikasi.')"
        :href="route('pengelola.notifikasi.index')">
        Pemberitahuan
    </x-nav-link>
@endif

@if (str_starts_with($routeName, 'account.'))
    <x-nav-link
        :active="str_starts_with($routeName, 'account.security')"
        :href="route('account.security')">
        Keamanan
    </x-nav-link>
@endif
