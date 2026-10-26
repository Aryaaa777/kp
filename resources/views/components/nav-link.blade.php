@props(['active' => false, 'href' => '#'])

<a href="{{ $href }}"
    @class([
        'block shrink-0 whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition',
        'bg-emerald-50 text-emerald-700' => $active,
        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
    ])
    @if ($active) aria-current="page" @endif
    {{ $attributes }}>
    {{ $slot }}
</a>
