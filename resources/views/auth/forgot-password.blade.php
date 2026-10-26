@extends('layouts.auth')

@section('title', 'Lupa Password · ' . config('app.name'))

@section('content')
    <h1 class="text-lg font-semibold text-slate-900">Lupa password</h1>
    <p class="mt-1 text-sm text-slate-500">
        Masukkan email akun Anda. Jika email terdaftar, kami akan mengirim tautan untuk membuat password baru.
    </p>

    @if (session('status'))
        <div class="mt-5">
            <x-alert type="success">{{ session('status') }}</x-alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-5" role="alert">
            <x-alert type="danger">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
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

        <button type="submit"
            class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Kirim tautan reset
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-slate-500">
        <a href="{{ route('login') }}" class="font-medium text-emerald-700 hover:underline">Kembali ke halaman masuk</a>
    </p>
@endsection
