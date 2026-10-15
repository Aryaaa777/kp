@php
    $cooperative = $cooperative ?? null;
    $action = $action ?? route('admin.koperasi.store');
    $method = $method ?? 'POST';
    $jenisOptions = $jenisOptions ?? [];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Identitas</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-form.input name="nama" label="Nama koperasi" :value="$cooperative?->nama" required />

            <x-form.input name="nomor_identitas" label="Nomor identitas" :value="$cooperative?->nomor_identitas" required
                help="Format resmi belum dikonfirmasi dinas (Q-001). Isi sesuai data yang tersedia; sistem hanya memastikan unik." />

            @if (count($jenisOptions))
                <x-form.select name="jenis_koperasi" label="Jenis koperasi" required
                    :options="collect($jenisOptions)->mapWithKeys(fn ($item) => [$item => $item])->all()"
                    :selected="$cooperative?->jenis_koperasi" />
            @else
                <x-form.input name="jenis_koperasi" label="Jenis koperasi" :value="$cooperative?->jenis_koperasi" required
                    help="Daftar jenis resmi belum dikonfirmasi dinas (Q-003); masukkan teks bebas sementara." />
            @endif

            <x-form.input name="bentuk_koperasi" label="Bentuk koperasi" :value="$cooperative?->bentuk_koperasi" />

            <x-form.input name="tanggal_berdiri" type="date" label="Tanggal berdiri"
                :value="$cooperative?->tanggal_berdiri?->format('Y-m-d')" />

            <x-form.select name="status_verifikasi" label="Status data" required
                :options="$statusOptions" :selected="$cooperative?->status_verifikasi ?? 'draft'"
                help="Penjaga matriks transisi resmi mengikuti workflow verifikasi (F5). Status “Diarsipkan” hanya melalui aksi arsip." />
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Wilayah &amp; kontak</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @if ($regions->isEmpty())
                <div class="sm:col-span-2">
                    <x-alert type="warning">
                        Referensi wilayah belum tersedia (menunggu konfirmasi sumber resmi — Q-012).
                        Kolom wilayah dikosongkan sementara dan dapat diisi setelah data wilayah di-seed.
                    </x-alert>
                </div>
            @else
                <x-form.select name="region_id" label="Kabupaten/Kota" placeholder="— Pilih wilayah —"
                    :options="$regions->pluck('name', 'id')->all()" :selected="$cooperative?->region_id" />
            @endif

            <x-form.input name="telephone" label="Telepon" :value="$cooperative?->telephone" />
            <x-form.input name="email" type="email" label="Email" :value="$cooperative?->email" />
            <x-form.input name="website" type="url" label="Website" :value="$cooperative?->website" placeholder="https://" />

            <div class="sm:col-span-2">
                <x-form.textarea name="alamat" label="Alamat" :value="$cooperative?->alamat" />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Simpan
        </button>
        <a href="{{ $cooperative ? route('admin.koperasi.show', $cooperative) : route('admin.koperasi.index') }}"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Batal
        </a>
    </div>
</form>
