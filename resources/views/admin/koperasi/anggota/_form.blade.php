@php
    $history = $history ?? null;
    $action = $action ?? route('admin.koperasi.anggota.store', $cooperative);
    $method = $method ?? 'POST';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Data perubahan keanggotaan</h2>
        <p class="mt-1 text-xs text-slate-500">
            Catat per kejadian. Minimal salah satu dari jumlah bertambah/berkurang wajib diisi
            dan keduanya tidak boleh nol. Jangan mencantumkan data pribadi anggota.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-form.input name="event_date" type="date" label="Tanggal kejadian" required
                :value="$history?->event_date?->format('Y-m-d') ?? date('Y-m-d')" />

            <x-form.select name="change_type" label="Jenis perubahan" required
                :options="$changeTypeOptions"
                :selected="$history?->change_type"
                placeholder="— Pilih jenis perubahan —"
                help="PROVISIONAL: daftar jenis belum resmi (Q-010)." />

            <x-form.input name="members_added" type="number" min="0" label="Jumlah bertambah (opsional)"
                :value="$history?->members_added" />

            <x-form.input name="members_removed" type="number" min="0" label="Jumlah berkurang (opsional)"
                :value="$history?->members_removed" />

            <div class="sm:col-span-2">
                <x-form.textarea name="notes" label="Catatan (opsional)" :value="$history?->notes" />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Simpan
        </button>
        <a href="{{ $history ? route('admin.koperasi.anggota.show', [$cooperative, $history]) : route('admin.koperasi.anggota.index', $cooperative) }}"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Batal
        </a>
    </div>
</form>
