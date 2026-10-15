@extends('layouts.auth')

@section('title', 'Masuk · ' . config('app.name'))

@section('content')
    <h1 class="text-lg font-semibold text-slate-900">Masuk ke akun</h1>
    <p class="mt-1 text-sm text-slate-500">Gunakan akun dinas atau akun koperasi Anda.</p>

    @if ($errors->any())
        <div class="mt-5" role="alert">
            <x-alert type="danger">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                required autofocus autocomplete="username"
                @class([
                    'mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1',
                    'border-red-400 focus:border-red-500 focus:ring-red-500' => $errors->has('email'),
                    'border-slate-300 focus:border-emerald-500 focus:ring-emerald-500' => ! $errors->has('email'),
                ])>
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                @class([
                    'mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1',
                    'border-red-400 focus:border-red-500 focus:ring-red-500' => $errors->has('password'),
                    'border-slate-300 focus:border-emerald-500 focus:ring-emerald-500' => ! $errors->has('password'),
                ])>
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1"
                class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            Ingat saya
        </label>

        <button type="submit"
            class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Masuk
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-slate-500">
        Belum punya akun? Hubungi admin dinas untuk pendaftaran.
    </p>
@endsection
