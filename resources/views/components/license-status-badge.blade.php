@props(['status'])

@php
    $colors = [
        'pending' => 'amber',
        'active' => 'emerald',
        'expired' => 'red',
        'revoked' => 'red',
    ];

    $label = \App\Models\CooperativeLicense::STATUS_LABELS[$status] ?? $status;
    $color = $colors[$status] ?? 'slate';
@endphp

<x-badge :color="$color">{{ $label }}</x-badge>
