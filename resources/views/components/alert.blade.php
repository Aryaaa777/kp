@props(['type' => 'info'])

@php
    $styles = [
        'info' => 'border-l-4 border-l-[#0051d5] border-[#dce9ff] bg-[#eff4ff] text-[#00174b]',
        'success' => 'border-l-4 border-l-emerald-600 border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-l-4 border-l-[#d77503] border-[#ffdcc3] bg-[#fff8f2] text-[#401f00]',
        'danger' => 'border-l-4 border-l-[#ba1a1a] border-[#ffdad6] bg-[#fff5f5] text-[#93000a]',
    ][$type] ?? 'border-l-4 border-l-[#0051d5] border-[#dce9ff] bg-[#eff4ff] text-[#00174b]';
@endphp

<div {{ $attributes->merge(['class' => "rounded-xl border p-4 text-xs sm:text-sm font-medium {$styles} shadow-2xs"]) }} role="alert">
    {{ $slot }}
</div>
