@props(['status'])

@php
    $statusValue = (string) $status;
    $label = match ($statusValue) {
        'active' => 'Available',
        'under_review', 'maintenance' => 'Busy',
        'inactive', 'retired', 'suspended' => 'Offline',
        'resolved', 'completed' => 'Completed',
        'pending' => 'Pending',
        'processing' => 'Processing',
        'ai_analyzing' => 'AI analyzing',
        'uploading' => 'Uploading',
        default => in_array($statusValue, \App\Models\IncidentReview::FAULT_DECISIONS, true)
            ? \App\Models\IncidentReview::faultDecisionLabel($statusValue)
            : ucfirst(str_replace('_', ' ', $statusValue)),
    };
@endphp

<span {{ $attributes->class(['status-badge', 'status-'.$statusValue]) }}>{{ $label }}</span>
