@props(['label', 'value', 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white p-5']) }}>
    <p class="text-sm text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $value }}</p>

    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @isset($footer)
        <div class="mt-3 text-sm">{{ $footer }}</div>
    @endisset
</div>
