<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#f8f9ff] text-[#0b1c30] antialiased">
    <div class="min-h-full flex flex-col">
        <header class="border-b border-[#dce9ff] bg-white sticky top-0 z-30 shadow-xs">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#001428] text-white shadow-xs">
                            <span class="material-symbols-outlined text-[20px] text-[#b0c9e8]">account_balance</span>
                        </div>
                        <div>
                            <span class="block text-sm font-bold tracking-tight text-[#001428] sm:text-base leading-tight">{{ config('app.name') }}</span>
                            <span class="block text-[10px] font-medium text-[#43474d]">Provinsi Jawa Barat</span>
                        </div>
                    </a>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 text-xs sm:text-sm">
                    @if (auth()->user()->hasRole('admin_dinas'))
                        <a href="{{ route('admin.akun.index') }}" class="font-medium text-[#43474d] hover:text-[#0051d5] transition-colors">Akun</a>
                        <a href="{{ route('admin.pengaturan.publik') }}" class="font-medium text-[#43474d] hover:text-[#0051d5] transition-colors">Pengaturan</a>
                    @endif
                    <a href="{{ route('account.security') }}" class="font-medium text-[#43474d] hover:text-[#0051d5] transition-colors">Keamanan</a>
                    <div class="hidden sm:flex items-center gap-1.5 rounded-lg bg-[#eff4ff] px-2.5 py-1 text-xs font-semibold text-[#001428]">
                        <span class="material-symbols-outlined text-[16px] text-[#0051d5]">person</span>
                        {{ auth()->user()->name }}
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-[#c3c6ce] px-3 py-1.5 text-xs font-bold text-[#001428] hover:bg-[#eff4ff] focus:outline-none focus:ring-2 focus:ring-[#0051d5] transition-colors">
                            <span class="material-symbols-outlined text-[16px]">logout</span>
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:flex-row">
            {{-- Navigasi Sidebar Responsif --}}
            <aside class="w-full shrink-0 lg:w-60">
                <div class="rounded-2xl border border-[#dce9ff] bg-white p-3 shadow-xs">
                    <nav class="flex gap-1 overflow-x-auto pb-1 lg:block lg:space-y-1 lg:overflow-visible lg:pb-0"
                        aria-label="Navigasi utama">
                        @include('layouts._sidebar')
                    </nav>
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                <div class="mb-5 border-b border-[#dce9ff] pb-4">
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-[#001428]">@yield('heading')</h1>
                    @hasSection('subheading')
                        <p class="mt-1 text-xs sm:text-sm text-[#43474d]">@yield('subheading')</p>
                    @endif
                </div>

                @if (session('status'))
                    <x-alert type="success" class="mb-5">{{ session('status') }}</x-alert>
                @endif

                @if (session('error'))
                    <x-alert type="danger" class="mb-5">{{ session('error') }}</x-alert>
                @endif

                @yield('content')
            </main>
        </div>

        <footer class="border-t border-[#dce9ff] bg-white mt-auto">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 text-xs text-[#43474d] sm:px-6">
                <span>&copy; {{ date('Y') }} Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat.</span>
                <span class="font-medium text-[#74777e]">Lingkungan Terverifikasi</span>
            </div>
        </footer>
    </div>
</body>
</html>
