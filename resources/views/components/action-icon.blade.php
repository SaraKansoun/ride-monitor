@props([
    'href' => null,
    'label',
    'name' => 'view',
    'type' => 'button',
])

@php
    $classes = ['action-icon-button'];
@endphp

@if ($href)
    <a {{ $attributes->class($classes)->merge(['href' => $href, 'aria-label' => $label, 'title' => $label]) }}>
        @include('components.partials.action-icon-svg', ['name' => $name])
    </a>
@else
    <button {{ $attributes->class($classes)->merge(['type' => $type, 'aria-label' => $label, 'title' => $label]) }}>
        @include('components.partials.action-icon-svg', ['name' => $name])
    </button>
@endif
