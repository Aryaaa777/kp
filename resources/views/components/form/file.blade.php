@props([
    'name',
    'label' => null,
    'required' => false,
    'help' => null,
    'accept' => null,
])

@php
    $hasError = $errors->has($name);
@endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
            {{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif
        </label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="file"
        @if ($accept) accept="{{ $accept }}" @endif
        @if ($required) required @endif
        {{ $attributes->merge([
            'class' => 'mt-1 block w-full rounded-md border px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 '
                . ($hasError
                    ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                    : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-500'),
        ]) }}>

    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif

    <x-form.error :name="$name" />
</div>
