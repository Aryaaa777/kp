@extends('layouts.internal')

@section('title', 'Pemberitahuan · ' . config('app.name'))
@section('heading', 'Pemberitahuan')
@section('subheading', 'Notifikasi status pengajuan Anda.')

@section('sidebar')
    <x-nav-link :href="route('pengelola.dashboard')">Dashboard</x-nav-link>
    <x-nav-link :href="route('pengelola.profil')">Profil Koperasi</x-nav-link>
    <x-nav-link :href="route('pengelola.izin.index')">Perizinan</x-nav-link>
    <x-nav-link :href="route('pengelola.pengurus.index')">Pengurus</x-nav-link>
    <x-nav-link :href="route('pengelola.kesehatan.index')">Kesehatan</x-nav-link>
    <x-nav-link :href="route('pengelola.tata-kelola.index')">Tata Kelola</x-nav-link>
    <x-nav-link :href="route('pengelola.aset.index')">Aset</x-nav-link>
    <x-nav-link :href="route('pengelola.anggota.index')">Anggota</x-nav-link>
    <x-nav-link :href="route('pengelola.pengajuan.index')">Pengajuan</x-nav-link>
    <x-nav-link :active="true" :href="route('pengelola.notifikasi.index')">Pemberitahuan</x-nav-link>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">
            Kanal in-app saja. Email/WA menunggu konfirmasi dinas (Q-011).
        </p>

        @if ($notifications->contains(fn ($notification) => $notification->read_at === null))
            <form method="POST" action="{{ route('pengelola.notifikasi.baca-semua') }}">
                @csrf
                <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Tandai semua dibaca
                </button>
            </form>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($notifications->isEmpty())
            <x-empty-state title="Belum ada pemberitahuan" message="Pemberitahuan status pengajuan akan muncul di sini." />
        @else
            <ul class="divide-y divide-slate-200">
                @foreach ($notifications as $notification)
                    @php $data = $notification->data; @endphp
                    <li class="px-5 py-4 {{ $notification->read_at === null ? 'bg-blue-50/50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $data['title'] ?? 'Pemberitahuan' }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $data['message'] ?? '' }}</p>
                                @if (! empty($data['reason']))
                                    <p class="mt-1 text-sm text-amber-700">Alasan: {{ $data['reason'] }}</p>
                                @endif
                                <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at?->translatedFormat('d M Y, H:i') }}</p>
                            </div>
                            <div class="text-right">
                                @if (! empty($data['status_label']))
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                        {{ $data['status_label'] }}
                                    </span>
                                @endif
                                @if (! empty($data['url']))
                                    <a href="{{ $data['url'] }}" class="mt-2 block text-sm text-blue-600 hover:underline">Lihat detail</a>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($notifications->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $notifications->links() }}</div>
            @endif
        @endif
    </div>
@endsection
