@props(['title' => 'Belum ada data', 'message' => null])

<div class="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
    <p class="text-sm font-medium text-slate-700">{{ $title }}</p>

    @if ($message)
        <p class="mt-1 text-sm text-slate-500">{{ $message }}</p>
    @endif

    {{ $slot }}
</div>
