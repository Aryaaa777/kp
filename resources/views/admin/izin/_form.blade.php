@php
    $license = $license ?? null;
    $action = $action ?? route('admin.koperasi.izin.store', $cooperative);
    $method = $method ?? 'POST';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Data izin</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-form.select name="license_type" label="Jenis izin" required
                :options="\App\Models\CooperativeLicense::typeLabels()"
                :selected="$license?->license_type ?? \App\Models\CooperativeLicense::TYPE_BUSINESS" />

            <x-form.input name="nomor" label="Nomor izin" :value="$license?->nomor" required
                help="Format nomor izin resmi belum dikonfirmasi dinas (Q-004). Sistem hanya memastikan unik per koperasi & jenis." />

            <x-form.input name="tanggal_terbit" type="date" label="Tanggal terbit"
                :value="$license?->tanggal_terbit?->format('Y-m-d')" required />

            <x-form.input name="tanggal_berlaku_sampai" type="date" label="Berlaku sampai"
                :value="$license?->tanggal_berlaku_sampai?->format('Y-m-d')"
                help="Kosongkan bila izin berlaku tanpa batas. Tidak boleh lebih awal daripada tanggal terbit." />

            <x-form.input name="penerbit" label="Penerbit" :value="$license?->penerbit" />

            <div class="sm:col-span-2">
                <x-form.textarea name="catatan" label="Catatan" :value="$license?->catatan" />
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            Status verifikasi dikelola melalui aksi <span class="font-medium">Verifikasi</span> pada halaman detail izin
            (FR-051/061) — bukan lewat form ini.
        </p>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Simpan
        </button>
        <a href="{{ $license ? route('admin.koperasi.izin.show', [$cooperative, $license]) : route('admin.koperasi.izin.index', $cooperative) }}"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Batal
        </a>
    </div>
</form>
