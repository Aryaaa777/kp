@php
    $routeName = request()->route()?->getName() ?? '';
    $user = auth()->user();
@endphp

@if ($user?->hasRole('admin_dinas'))
    <x-nav-link
        :active="str_starts_with($routeName, 'admin.dashboard')"
        :href="route('admin.dashboard')">
        <span class="material-symbols-outlined text-[18px]">dashboard</span>
        <span>Dashboard</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.koperasi.')"
        :href="route('admin.koperasi.index')">
        <span class="material-symbols-outlined text-[18px]">domain</span>
        <span>Data Koperasi</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.akun.')"
        :href="route('admin.akun.index')">
        <span class="material-symbols-outlined text-[18px]">manage_accounts</span>
        <span>Akun Pengelola</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.pengaturan.')"
        :href="route('admin.pengaturan.publik')">
        <span class="material-symbols-outlined text-[18px]">tune</span>
        <span>Pengaturan Publik</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.laporan.')"
        :href="route('admin.laporan.index')">
        <span class="material-symbols-outlined text-[18px]">description</span>
        <span>Laporan</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.pengajuan.')"
        :href="route('admin.pengajuan.index')">
        <span class="material-symbols-outlined text-[18px]">fact_check</span>
        <span>Pengajuan</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'admin.audit-log.')"
        :href="route('admin.audit-log.index')">
        <span class="material-symbols-outlined text-[18px]">history</span>
        <span>Audit Log</span>
    </x-nav-link>
@elseif ($user?->hasRole('pengelola_koperasi'))
    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.dashboard')"
        :href="route('pengelola.dashboard')">
        <span class="material-symbols-outlined text-[18px]">dashboard</span>
        <span>Dashboard</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.profil')"
        :href="route('pengelola.profil')">
        <span class="material-symbols-outlined text-[18px]">store</span>
        <span>Profil Koperasi</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.izin.')"
        :href="route('pengelola.izin.index')">
        <span class="material-symbols-outlined text-[18px]">badge</span>
        <span>Perizinan &amp; Dokumen</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.pengurus.')"
        :href="route('pengelola.pengurus.index')">
        <span class="material-symbols-outlined text-[18px]">badge</span>
        <span>Pengurus</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.kesehatan.')"
        :href="route('pengelola.kesehatan.index')">
        <span class="material-symbols-outlined text-[18px]">ecg_heart</span>
        <span>Kesehatan</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.tata-kelola.')"
        :href="route('pengelola.tata-kelola.index')">
        <span class="material-symbols-outlined text-[18px]">policy</span>
        <span>Tata Kelola</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.aset.')"
        :href="route('pengelola.aset.index')">
        <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
        <span>Aset</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.anggota.')"
        :href="route('pengelola.anggota.index')">
        <span class="material-symbols-outlined text-[18px]">group</span>
        <span>Anggota</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.pengajuan.')"
        :href="route('pengelola.pengajuan.index')">
        <span class="material-symbols-outlined text-[18px]">send</span>
        <span>Pengajuan</span>
    </x-nav-link>

    <x-nav-link
        :active="str_starts_with($routeName, 'pengelola.notifikasi.')"
        :href="route('pengelola.notifikasi.index')">
        <span class="material-symbols-outlined text-[18px]">notifications</span>
        <span>Pemberitahuan</span>
    </x-nav-link>
@endif

@if (str_starts_with($routeName, 'account.'))
    <x-nav-link
        :active="str_starts_with($routeName, 'account.security')"
        :href="route('account.security')">
        <span class="material-symbols-outlined text-[18px]">security</span>
        <span>Keamanan</span>
    </x-nav-link>
@endif
