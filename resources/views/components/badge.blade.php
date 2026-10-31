@props(['color' => 'slate'])

@php
    $styles = [
        'slate' => 'bg-[#eff4ff] text-[#43474d] ring-[#dce9ff]',
        'emerald' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/30',
        'blue' => 'bg-[#dbe1ff] text-[#00174b] ring-[#b4c5ff]',
        'amber' => 'bg-[#ffdcc3] text-[#401f00] ring-[#d77503]/30',
        'red' => 'bg-[#ffdad6] text-[#93000a] ring-[#ba1a1a]/30',
    ][$color] ?? 'bg-[#eff4ff] text-[#43474d] ring-[#dce9ff]';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-lg px-2.5 py-0.5 text-xs font-semibold ring-1 {$styles}"]) }}>
    {{ $slot }}
</span>
