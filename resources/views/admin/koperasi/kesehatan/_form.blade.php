@php
    $assessment = $assessment ?? null;
    $action = $action ?? route('admin.koperasi.kesehatan.store', $cooperative);
    $method = $method ?? 'POST';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Data penilaian</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-form.input name="period_year" type="number" label="Tahun periode" required
                :value="$assessment?->period_year ?? date('Y')" />

            <x-form.select name="period" label="Periode" required
                :options="$periodOptions"
                :selected="$assessment?->period ?? 'annual'"
                help="PROVISIONAL: untuk sementara hanya tahunan (Q-010)." />

            <x-form.input name="assessment_date" type="date" label="Tanggal penilaian" required
                :value="$assessment?->assessment_date?->format('Y-m-d')" />

            <div class="sm:col-span-2">
                <x-form.checkbox name="auto_calculate" label="Hitung skor otomatis dari komponen"
                    :checked="$assessment?->auto_calculated ?? false"
                    help="PROVISIONAL: bobot dan ambang ada di config/koperasi.php. Bukan formula resmi (Q-007)." />
            </div>

            <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
                @foreach ($componentLabels as $key => $label)
                    @php($field = 'component_'.$key)
                    <x-form.input :name="$field" type="number" step="0.01" min="0" max="100"
                        :label="$label.' (0–100)'"
                        :value="$assessment?->getAttribute($field)"
                        help="Wajib bila hitung otomatis dicentang." />
                @endforeach
            </div>

            <x-form.select name="status" label="Status catatan"
                :options="$statusOptions"
                :selected="$assessment?->status ?? 'draft'"
                help="'Final' hanya penanda internal, bukan pengesahan resmi." />

            <x-form.input name="score" type="number" step="0.01" min="0" max="100" label="Skor (opsional)"
                :value="$assessment?->score"
                help="Input manual. TIDAK ada formula resmi (Q-007)." />

            <x-form.input name="predicate" label="Predikat (opsional)"
                :value="$assessment?->predicate"
                help="Teks bebas. Bukan klasifikasi resmi dinas." />

            <div class="sm:col-span-2">
                <x-form.textarea name="notes" label="Catatan pendukung" :value="$assessment?->notes" />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Simpan
        </button>
        <a href="{{ $assessment ? route('admin.koperasi.kesehatan.show', [$cooperative, $assessment]) : route('admin.koperasi.kesehatan.index', $cooperative) }}"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Batal
        </a>
    </div>
</form>
