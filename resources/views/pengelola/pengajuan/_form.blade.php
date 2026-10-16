@php
    $action = $action ?? route('pengelola.pengajuan.store');
    $method = $method ?? 'POST';
    $submission = $submission ?? null;
    $values = $values ?? [];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if (count($modules) > 1)
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <x-form.select name="module" label="Modul data" :options="$modules" :selected="$module" required
                help="Pilih modul data yang ingin diubah." />
        </div>
    @else
        <input type="hidden" name="module" value="{{ $module }}">
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Usulan Perubahan</h2>
        <p class="mt-1 text-xs text-slate-500">
            Isi nilai yang diusulkan. Field yang tidak diubah boleh dibiarkan sesuai data resmi.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($fields as $field => $definition)
                @php
                    $value = $values[$field] ?? null;
                    $required = in_array('required', $definition['rules'] ?? [], true);
                @endphp

                @if ($definition['type'] === 'textarea')
                    <div class="sm:col-span-2">
                        <x-form.textarea :name="$field" :label="$definition['label']" :value="$value" :required="$required" />
                    </div>
                @else
                    <x-form.input :name="$field" :label="$definition['label']" :type="$definition['type']" :value="$value" :required="$required" />
                @endif
            @endforeach

            <div class="sm:col-span-2">
                <x-form.input name="title" label="Judul pengajuan (opsional)" :value="$submission?->title"
                    help="Kosongkan untuk memakai judul otomatis." />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
            Simpan Draft
        </button>
        <a href="{{ $submission ? route('pengelola.pengajuan.show', $submission) : route('pengelola.pengajuan.index') }}"
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Batal
        </a>
    </div>
</form>
