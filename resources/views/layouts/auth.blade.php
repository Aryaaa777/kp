<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex items-center justify-center gap-2">
                <span class="grid h-9 w-9 place-items-center rounded-md bg-emerald-600 text-sm font-bold text-white">KJ</span>
                <span class="text-base font-semibold text-slate-900">{{ config('app.name') }}</span>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @yield('content')
            </div>

            <p class="mt-4 text-center text-xs text-slate-500">
                &copy; {{ date('Y') }} Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat
            </p>
        </div>
    </div>
</body>
</html>
