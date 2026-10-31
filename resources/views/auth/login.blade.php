@extends('layouts.auth')

@section('title', 'Masuk · ' . config('app.name'))

@section('content')
    <div class="flex flex-col items-center text-center mb-6">
        <h1 class="text-xl font-bold tracking-tight text-[#001428]">Masuk ke akun</h1>
        <p class="mt-1 text-xs sm:text-sm text-[#43474d]">Gunakan akun dinas atau akun koperasi Anda.</p>
    </div>

    <div class="mb-5 rounded-xl bg-[#eff4ff] border border-[#dce9ff] p-3.5 flex items-start gap-3 text-left">
        <span class="material-symbols-outlined text-[#0051d5] text-[20px] shrink-0 mt-0.5">shield_person</span>
        <div class="flex flex-col text-xs">
            <span class="font-bold text-[#001428]">Otentikasi Terpusat Jabar</span>
            <span class="text-[#43474d] mt-0.5">Hak akses dinas dan pengelola koperasi diverifikasi otomatis oleh sistem.</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-5" role="alert">
            <x-alert type="danger">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4 text-left">
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

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-xs font-bold text-[#001428] uppercase tracking-wider">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-[#0051d5] hover:underline">
                    Lupa password?
                </a>
            </div>
            <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-3.5 text-[#74777e] text-[18px] pointer-events-none">lock</span>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    placeholder="Masukkan kata sandi akun"
                    @class([
                        'block w-full pl-10 pr-10 py-2.5 rounded-xl border text-xs sm:text-sm bg-white shadow-2xs transition-all focus:outline-none focus:ring-2 focus:ring-[#0051d5]/20',
                        'border-[#ba1a1a] focus:border-[#ba1a1a]' => $errors->has('password'),
                        'border-[#c3c6ce] focus:border-[#0051d5]' => ! $errors->has('password'),
                    ])>
                <button type="button" aria-label="Lihat password" id="togglePassword"
                    class="absolute right-3 text-[#74777e] hover:text-[#001428] p-1 rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-[18px]" id="passwordIcon">visibility</span>
                </button>
            </div>
            @error('password')
                <p class="mt-1 text-xs text-[#ba1a1a]">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-3 text-xs text-[#43474d] pt-1">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="remember" value="1"
                    class="h-4 w-4 rounded border-[#c3c6ce] text-[#0051d5] focus:ring-[#0051d5]/30">
                <span>Ingat saya</span>
            </label>
        </div>

        <button type="submit"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#001428] py-2.5 px-4 text-xs sm:text-sm font-bold text-white shadow-md hover:bg-[#0f2942] focus:outline-none focus:ring-2 focus:ring-[#0051d5] transition-all">
            <span>Masuk</span>
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
    </form>

    <div class="mt-6 rounded-xl bg-[#eff4ff] p-3 text-center border border-[#dce9ff]">
        <p class="text-xs text-[#43474d]">
            Belum punya akun?
            <a href="mailto:diskuk@jabarprov.go.id" class="font-bold text-[#0051d5] hover:underline">
                Hubungi Admin Dinas
            </a>
        </p>
    </div>

    <script>
        document.getElementById('togglePassword')?.addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        });
    </script>
@endsection
