<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#f8f9ff] text-[#0b1c30] antialiased">
    <div class="flex min-h-full flex-col">
        <!-- Status Strip Bar (Development Preview) -->
        <div class="w-full bg-[#e5eeff] border-b border-[#dce9ff] px-4 sm:px-6 py-2 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#ffdcc3] text-[#2f1500]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#d77503] animate-pulse"></span>
                    Pratinjau pengembangan — belum rilis resmi
                </span>
                <span class="hidden md:inline text-xs text-[#43474d]">
                    Sistem Informasi Koperasi Jawa Barat • Dinas Koperasi dan Usaha Kecil Prov. Jabar
                </span>
            </div>
            <div class="flex items-center gap-1 text-[#43474d] text-xs font-medium">
                <span class="material-symbols-outlined text-[16px] text-[#0051d5]">verified_user</span>
                <span class="hidden sm:inline">Portal Resmi</span>
            </div>
        </div>

        <header class="border-b border-[#dce9ff] bg-white sticky top-0 z-30 shadow-xs">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3.5 sm:px-6">
                <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#001428] text-white shadow-xs group-hover:bg-[#0f2942] transition-colors">
                        <span class="material-symbols-outlined text-[22px] text-[#b0c9e8]">account_balance</span>
                    </div>
                    <div>
                        <span class="block text-sm font-bold tracking-tight text-[#001428] sm:text-base leading-tight">{{ config('app.name') }}</span>
                        <span class="block text-[11px] font-medium text-[#43474d]">Provinsi Jawa Barat</span>
                    </div>
                </a>
                <nav class="hidden items-center gap-5 text-sm font-medium text-[#43474d] sm:flex">
                    <a href="{{ route('home') }}" class="hover:text-[#0051d5] transition-colors {{ request()->routeIs('home') ? 'text-[#0051d5] font-semibold' : '' }}">Beranda</a>
                    <a href="{{ route('publik.direktori') }}" class="hover:text-[#0051d5] transition-colors {{ request()->routeIs('publik.direktori*') ? 'text-[#0051d5] font-semibold' : '' }}">Direktori Koperasi</a>
                    <a href="{{ route('publik.statistik') }}" class="hover:text-[#0051d5] transition-colors {{ request()->routeIs('publik.statistik') ? 'text-[#0051d5] font-semibold' : '' }}">Statistik</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="hover:text-[#0051d5] transition-colors">Dashboard</a>
                        <span class="text-xs text-[#74777e] border-l border-[#c3c6ce] pl-3 py-1">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-[#c3c6ce] px-3 py-1.5 text-xs font-semibold text-[#001428] hover:bg-[#eff4ff] transition-colors">Keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-[#001428] px-4 py-2 text-xs font-bold text-white hover:bg-[#0f2942] shadow-sm transition-all">
                            <span class="material-symbols-outlined text-[16px]">login</span>
                            Masuk
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-[#dce9ff] bg-white mt-auto">
            <div class="mx-auto flex max-w-6xl flex-col sm:flex-row items-center justify-between gap-3 px-4 py-6 text-xs text-[#43474d] sm:px-6">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-[#001428]">SIM-KOP Jabar</span>
                    <span>&copy; {{ date('Y') }} Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat.</span>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <a href="{{ route('publik.direktori') }}" class="hover:text-[#0051d5]">Direktori Publik</a>
                    <a href="{{ route('publik.statistik') }}" class="hover:text-[#0051d5]">Statistik Wilayah</a>
                    <a href="{{ route('login') }}" class="hover:text-[#0051d5]">Portal Petugas</a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
