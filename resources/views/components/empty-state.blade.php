@props(['title' => 'Belum ada data', 'message' => null])

<div class="rounded-2xl border-2 border-dashed border-[#c3c6ce] bg-white px-6 py-12 text-center shadow-xs">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#eff4ff] text-[#0051d5] mb-3">
        <span class="material-symbols-outlined text-[26px]">folder_open</span>
    </div>
    <p class="text-sm font-bold text-[#001428]">{{ $title }}</p>

    @if ($message)
        <p class="mt-1.5 text-xs text-[#43474d] max-w-sm mx-auto leading-relaxed">{{ $message }}</p>
    @endif

    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
