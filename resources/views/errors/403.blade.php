<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses ditolak</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased">
    <div class="flex min-h-full flex-col">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-md bg-emerald-600 text-sm font-bold text-white">KJ</span>
                    <span class="text-sm font-semibold text-slate-900 sm:text-base">{{ config('app.name') }}</span>
                </a>
                <nav class="flex items-center gap-4 text-sm text-slate-600">
                    <a href="{{ route('publik.direktori') }}" class="hover:text-emerald-700">Direktori Koperasi</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="hover:text-emerald-700">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">Masuk</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-center px-4 py-16 sm:px-6">
                <div class="text-center">
                    <h1 class="text-6xl font-bold text-slate-900">403</h1>
                    <p class="mt-4 text-xl text-slate-600">Akses ditolak.</p>
                    <p class="mt-2 text-sm text-slate-500">
                        Anda tidak memiliki izin untuk mengakses area ini.
                        @auth
                            Jika ini adalah kesalahan, hubungi administrator.
                        @else
                            Coba masuk ke sistem dengan akun Anda.
                        @endauth
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                Buka Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                Masuk ke Sistem
                            </a>
                        @endauth
                        <a href="{{ url('/') }}"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-4 text-xs text-slate-500 sm:px-6">
                &copy; {{ date('Y') }} Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat.
            </div>
        </footer>
    </div>
</body>
</html>
