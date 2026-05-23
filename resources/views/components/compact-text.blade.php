@props(['text', 'words' => 5])

@php
    $textValue = (string) $text;
    $displayText = \Illuminate\Support\Str::words($textValue, (int) $words, '...');
@endphp

<span {{ $attributes->class(['table-compact-text'])->merge(['title' => $textValue]) }}>{{ $displayText }}</span>
