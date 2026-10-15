@props(['type' => 'info'])

@php
    $styles = [
        'info' => 'border-slate-200 bg-slate-50 text-slate-700',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
    ][$type] ?? 'border-slate-200 bg-slate-50 text-slate-700';
@endphp

<div {{ $attributes->merge(['class' => "rounded-md border px-3 py-2 text-sm {$styles}"]) }} role="alert">
    {{ $slot }}
</div>
