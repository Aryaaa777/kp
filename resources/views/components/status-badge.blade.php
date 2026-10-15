@props(['status'])

@php
    $colors = [
        'draft' => 'slate',
        'submitted' => 'blue',
        'under_review' => 'amber',
        'needs_revision' => 'amber',
        'approved' => 'emerald',
        'rejected' => 'red',
        'archived' => 'slate',
    ];

    $label = \App\Models\Cooperative::STATUS_LABELS[$status] ?? $status;
    $color = $colors[$status] ?? 'slate';
@endphp

<x-badge :color="$color">{{ $label }}</x-badge>
