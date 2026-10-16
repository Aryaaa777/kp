@php
    $asset = $asset ?? null;
    $action = $action ?? route('admin.koperasi.aset.store', $cooperative);
    $method = $method ?? 'POST';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Data aset</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-form.input name="period_year" type="number" label="Tahun periode" required
                :value="$asset?->period_year ?? date('Y')" />

            @if (! empty($typeOptions))
                <x-form.select name="asset_type" label="Jenis aset" required
                    :options="collect($typeOptions)->mapWithKeys(fn ($t) => [$t => $t])->all()"
                    :selected="$asset?->asset_type"
                    placeholder="— Pilih jenis aset —"
                    help="PROVISIONAL: daftar saran, dapat berubah (Q-010)." />
            @else
                <x-form.input name="asset_type" label="Jenis aset" required
                    :value="$asset?->asset_type"
                    help="PROVISIONAL: teks bebas; taksonomi resmi belum ditetapkan (Q-010)." />
            @endif

            <x-form.input name="asset_name" label="Nama aset (opsional)" :value="$asset?->asset_name" />

            <x-form.input name="quantity" type="number" min="0" label="Jumlah (opsional)"
                :value="$asset?->quantity"
                help="PROVISIONAL: belum ditetapkan Data Dictionary." />

            <x-form.input name="value" type="number" step="0.01" min="0" label="Nilai (Rp)" required
                :value="$asset?->value"
                help="Input manual. Tidak ada perhitungan akuntansi otomatis." />

            <x-form.input name="acquisition_date" type="date" label="Tanggal perolehan (opsional)"
                :value="$asset?->acquisition_date?->format('Y-m-d')"
                help="PROVISIONAL: belum ditetapkan Data Dictionary." />

            @if (! empty($conditionOptions))
                <x-form.select name="condition" label="Kondisi (opsional)"
                    :options="$conditionOptions"
                    :selected="$asset?->condition"
                    placeholder="— Tidak diisi —"
                    help="PROVISIONAL: pilihan kondisi belum resmi (Q-010)." />
            @else
                <x-form.input name="condition" label="Kondisi (opsional)" :value="$asset?->condition" />
            @endif

            <div class="sm:col-span-2">
                <x-form.textarea name="description" label="Keterangan (opsional)" :value="$asset?->description" />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Simpan
        </button>
        <a href="{{ $asset ? route('admin.koperasi.aset.show', [$cooperative, $asset]) : route('admin.koperasi.aset.index', $cooperative) }}"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Batal
        </a>
    </div>
</form>
