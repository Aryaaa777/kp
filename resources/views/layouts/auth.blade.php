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
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-12 relative overflow-hidden">
        <!-- Ambient Background Effect -->
        <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-[#dbe1ff]/40 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-24 -bottom-24 h-96 w-96 rounded-full bg-[#dce9ff]/50 blur-3xl pointer-events-none"></div>

        <div class="w-full max-w-[460px] relative z-10">
            <div class="mb-6 flex flex-col items-center text-center">
                <a href="{{ url('/') }}" class="group mb-3 flex flex-col items-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#001428] text-white shadow-md group-hover:bg-[#0f2942] transition-colors">
                        <span class="material-symbols-outlined text-[30px] text-[#b0c9e8]">account_balance</span>
                    </div>
                </a>
                <h2 class="text-base font-extrabold tracking-tight text-[#001428]">{{ config('app.name') }}</h2>
                <span class="text-xs font-semibold uppercase tracking-wider text-[#0051d5]">Provinsi Jawa Barat</span>
            </div>

            <div class="rounded-2xl border border-[#dce9ff] bg-white p-6 sm:p-8 shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-[#001428] via-[#0051d5] to-[#0f2942]"></div>
                @yield('content')
            </div>

            <p class="mt-6 text-center text-xs text-[#74777e]">
                &copy; {{ date('Y') }} Dinas Koperasi dan Usaha Kecil Provinsi Jawa Barat
            </p>
        </div>
    </div>
</body>
</html>
