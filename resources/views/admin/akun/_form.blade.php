@php
    $currentRole = old('role', $user->roles->first()?->name ?? 'pengelola_koperasi');
    $currentKoperasi = old('koperasi_id', $user->koperasi_id);
    $currentRegion = old('region_id', $user->region_id);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <x-alert type="danger">{{ $errors->first() }}</x-alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Nama</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>

        <div>
            <label for="role" class="block text-sm font-medium text-slate-700">Peran</label>
            <select id="role" name="role" required
                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                @foreach ($roleLabels as $value => $label)
                    <option value="{{ $value }}" @selected($currentRole === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Admin dinas tidak tertaut ke koperasi.</p>
        </div>

        <div>
            <label for="koperasi_id" class="block text-sm font-medium text-slate-700">Koperasi (untuk pengelola)</label>
            <select id="koperasi_id" name="koperasi_id"
                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Belum ditautkan</option>
                @foreach ($cooperatives as $cooperative)
                    <option value="{{ $cooperative->id }}" @selected((string) $currentKoperasi === (string) $cooperative->id)>
                        {{ $cooperative->nama }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Wajib konsisten dengan peran; nilai ini divalidasi di server.</p>
        </div>

        <div>
            <label for="region_id" class="block text-sm font-medium text-slate-700">Wilayah (untuk admin wilayah)</label>
            <select id="region_id" name="region_id"
                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Belum ditetapkan</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) $currentRegion === (string) $region->id)>
                        {{ $region->name }} ({{ $region->type }})
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">
                Wajib untuk peran Admin Wilayah. PROVISIONAL: pembatasan wilayah dapat dinonaktifkan lewat konfigurasi.
            </p>
        </div>
    </div>

    @if (! $user->exists)
        <x-alert type="info">
            Password awal dibuat acak oleh sistem dan tidak ditampilkan. Setelah akun dibuat, tautan reset password
            akan dikirim ke email akun.
        </x-alert>
    @endif

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('admin.akun.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
    </div>
</form>
