@props(['active' => false, 'href' => '#'])

<a href="{{ $href }}"
    @class([
        'group flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-semibold tracking-wide transition-all duration-150',
        'bg-[#001428] text-white shadow-xs font-bold' => $active,
        'text-[#43474d] hover:bg-[#eff4ff] hover:text-[#001428]' => ! $active,
    ])
    @if ($active) aria-current="page" @endif
    {{ $attributes }}>
    {{ $slot }}
</a>
