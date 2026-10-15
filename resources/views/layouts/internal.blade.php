<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
    <div class="min-h-full">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-md bg-emerald-600 text-sm font-bold text-white">KJ</span>
                    <span class="text-sm font-semibold text-slate-900 sm:text-base">{{ config('app.name') }}</span>
                </div>

                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-slate-600 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6 sm:px-6">
            <aside class="hidden w-56 shrink-0 lg:block">
                <nav class="space-y-1" aria-label="Navigasi utama">
                    @yield('sidebar')
                </nav>
            </aside>

            <main class="min-w-0 flex-1">
                <div class="mb-4">
                    <h1 class="text-xl font-bold text-slate-900">@yield('heading')</h1>
                    @hasSection('subheading')
                        <p class="mt-1 text-sm text-slate-500">@yield('subheading')</p>
                    @endif
                </div>

                @if (session('status'))
                    <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
