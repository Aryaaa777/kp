<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
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
                <nav class="hidden items-center gap-4 text-sm text-slate-600 sm:flex">
                    <a href="{{ url('/') }}" class="hover:text-emerald-700">Beranda</a>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-4 text-xs text-slate-500 sm:px-6">
                &copy; {{ date('Y') }} Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat.
            </div>
        </footer>
    </div>
</body>
</html>
