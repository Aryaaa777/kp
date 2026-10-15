@props([
    'name',
    'label' => null,
    'checked' => false,
    'help' => null,
])

@php
    $hasError = $errors->has($name);
@endphp

<div>
    <div class="flex items-start">
        <div class="flex h-5 items-center">
            <input
                id="{{ $name }}"
                name="{{ $name }}"
                type="checkbox"
                value="1"
                @if (old($name, $checked)) checked @endif
                {{ $attributes->merge([
                    'class' => 'h-4 w-4 rounded border text-emerald-600 focus:ring-emerald-500 '
                        . ($hasError
                            ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
                            : 'border-slate-300'),
                ]) }}>
        </div>
        @if ($label)
            <div class="ml-3 text-sm">
                <label for="{{ $name }}" class="font-medium text-slate-700">
                    {{ $label }}
                </label>
                @if ($help)
                    <p class="text-slate-500">{{ $help }}</p>
                @endif
            </div>
        @endif
    </div>

    <x-form.error :name="$name" />
</div>
