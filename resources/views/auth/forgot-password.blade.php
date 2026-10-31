@extends('layouts.auth')

@section('title', 'Lupa Password · ' . config('app.name'))

@section('content')
    <div class="flex flex-col items-center text-center mb-6">
        <h1 class="text-xl font-bold tracking-tight text-[#001428]">Lupa password</h1>
        <p class="mt-1 text-xs sm:text-sm text-[#43474d] max-w-sm">
            Masukkan email akun Anda. Jika email terdaftar, kami akan mengirim tautan untuk membuat password baru.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-5">
            <x-alert type="success">{{ session('status') }}</x-alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5" role="alert">
            <x-alert type="danger">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4 text-left">
        @csrf

        <div>
            <label for="email" class="block text-xs font-bold text-[#001428] uppercase tracking-wider mb-1.5">Email</label>
            <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-3.5 text-[#74777e] text-[18px] pointer-events-none">mail</span>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                    required autofocus autocomplete="username"
                    placeholder="nama@instansi.go.id atau koperasi@gmail.com"
                    @class([
                        'block w-full pl-10 pr-3.5 py-2.5 rounded-xl border text-xs sm:text-sm bg-white shadow-2xs transition-all focus:outline-none focus:ring-2 focus:ring-[#0051d5]/20',
                        'border-[#ba1a1a] focus:border-[#ba1a1a]' => $errors->has('email'),
                        'border-[#c3c6ce] focus:border-[#0051d5]' => ! $errors->has('email'),
                    ])>
            </div>
            @error('email')
                <p class="mt-1 text-xs text-[#ba1a1a]">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#001428] py-2.5 px-4 text-xs sm:text-sm font-bold text-white shadow-md hover:bg-[#0f2942] focus:outline-none focus:ring-2 focus:ring-[#0051d5] transition-all">
            <span>Kirim tautan reset</span>
            <span class="material-symbols-outlined text-[18px]">send</span>
        </button>
    </form>

    <div class="mt-6 rounded-xl bg-[#eff4ff] p-3 text-center border border-[#dce9ff]">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0051d5] hover:underline">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Kembali ke halaman masuk
        </a>
    </div>
@endsection
