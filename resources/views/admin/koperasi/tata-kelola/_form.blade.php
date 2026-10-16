@php
    $record = $record ?? null;
    $action = $action ?? route('admin.koperasi.tata-kelola.store', $cooperative);
    $method = $method ?? 'POST';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Data tata kelola</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-form.input name="period_year" type="number" label="Tahun periode" required
                :value="$record?->period_year ?? date('Y')" />

            <x-form.select name="period" label="Periode" required
                :options="$periodOptions"
                :selected="$record?->period ?? 'annual'"
                help="PROVISIONAL: untuk sementara hanya tahunan (Q-010)." />

            <x-form.input name="meeting_count" type="number" min="0" label="Jumlah rapat (opsional)"
                :value="$record?->meeting_count"
                help="Contoh indikator. Bukan indikator resmi." />

            <x-form.input name="governance_score" type="number" step="0.01" min="0" max="100" label="Skor (opsional)"
                :value="$record?->governance_score"
                help="Input manual. Tidak ada formula resmi." />

            <x-form.select name="status" label="Status catatan"
                :options="$statusOptions"
                :selected="$record?->status ?? 'draft'"
                help="'Final' hanya penanda internal, bukan pengesahan resmi." />

            <div class="sm:col-span-2">
                <x-form.textarea name="summary" label="Ringkasan/catatan" :value="$record?->summary" />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Simpan
        </button>
        <a href="{{ $record ? route('admin.koperasi.tata-kelola.show', [$cooperative, $record]) : route('admin.koperasi.tata-kelola.index', $cooperative) }}"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Batal
        </a>
    </div>
</form>
