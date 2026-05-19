@props(['name' => 'dashboard'])

<span {{ $attributes->class(['nav-icon']) }} aria-hidden="true">
    @switch($name)
        @case('incidents')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 3.5 20 7v5c0 4.8-3.3 7.6-8 8.5-4.7-.9-8-3.7-8-8.5V7l8-3.5Z" />
                <path d="M12 8v4" />
                <path d="M12 16h.01" />
            </svg>
        @break

        @case('ai')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M8 4h8a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Z" />
                <path d="M9 9h6" />
                <path d="M9 13h3" />
                <path d="M16.5 14.5 18 16l-1.5 1.5L15 16l1.5-1.5Z" />
            </svg>
        @break

        @case('reviews')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M6 4h9l3 3v13H6V4Z" />
                <path d="M15 4v4h4" />
                <path d="m8.5 14 2 2 5-5" />
            </svg>
        @break

        @case('safety')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 20a8 8 0 1 0-8-8" />
                <path d="M12 12 17 7" />
                <path d="M7 17h10" />
            </svg>
        @break

        @case('users')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                <path d="M3 21a6 6 0 0 1 12 0" />
                <path d="M17 8a3 3 0 1 1 0 6" />
                <path d="M17 17a5 5 0 0 1 4 4" />
            </svg>
        @break

        @case('drivers')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M8 10a4 4 0 1 0 8 0 4 4 0 0 0-8 0Z" />
                <path d="M4 21a8 8 0 0 1 16 0" />
                <path d="M9 4h6" />
            </svg>
        @break

        @case('vehicles')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M5 15h14l-1.2-4.5A3 3 0 0 0 15 8H9a3 3 0 0 0-2.8 2.5L5 15Z" />
                <path d="M4 15v4" />
                <path d="M20 15v4" />
                <path d="M7 19h.01" />
                <path d="M17 19h.01" />
            </svg>
        @break

        @case('assignments')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M7 7h10" />
                <path d="m14 4 3 3-3 3" />
                <path d="M17 17H7" />
                <path d="m10 14-3 3 3 3" />
            </svg>
        @break

        @case('performance')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M4 18h16" />
                <path d="M7 18v-5" />
                <path d="M12 18V7" />
                <path d="M17 18v-8" />
            </svg>
        @break

        @case('pending')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 6v6l4 2" />
                <path d="M21 12a9 9 0 1 1-9-9" />
            </svg>
        @break

        @case('completed')
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M20 6 9 17l-5-5" />
            </svg>
        @break

        @default
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M4 11.5 12 5l8 6.5V20H4v-8.5Z" />
                <path d="M9 20v-6h6v6" />
            </svg>
    @endswitch
</span>
