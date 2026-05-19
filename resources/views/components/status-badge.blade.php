@props(['status'])

@php
    $statusValue = (string) $status;
    $label = match ($statusValue) {
        'active' => 'Available',
        'under_review', 'maintenance' => 'Busy',
        'inactive', 'retired', 'suspended' => 'Offline',
        'resolved', 'completed' => 'Completed',
        'pending' => 'Pending',
        default => str_replace('_', ' ', $statusValue),
    };
@endphp

<span {{ $attributes->class(['status-badge', 'status-'.$statusValue]) }}>{{ $label }}</span>
