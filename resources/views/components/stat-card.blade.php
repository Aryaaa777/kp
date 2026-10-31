@props(['label', 'value', 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-[#dce9ff] bg-white p-5 shadow-xs relative overflow-hidden transition-all hover:shadow-sm']) }}>
    <p class="text-xs font-bold uppercase tracking-wider text-[#43474d]">{{ $label }}</p>
    <p class="mt-2 text-3xl font-extrabold tracking-tight text-[#001428]">{{ $value }}</p>

    @if ($hint)
        <p class="mt-2 text-xs text-[#74777e] leading-relaxed">{{ $hint }}</p>
    @endif

    @isset($footer)
        <div class="mt-4 border-t border-[#eff4ff] pt-3 text-xs">{{ $footer }}</div>
    @endisset
</div>
