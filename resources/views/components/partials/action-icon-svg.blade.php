@switch($name)
    @case('start')
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M8 5v14l11-7-11-7Z" />
        </svg>
    @break

    @case('edit')
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" />
            <path d="m14 8 2 2" />
        </svg>
    @break

    @case('deactivate')
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M7 5v14" />
            <path d="M17 5v14" />
        </svg>
    @break

    @case('reactivate')
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M20 12a8 8 0 1 1-2.3-5.7" />
            <path d="M20 4v5h-5" />
        </svg>
    @break

    @case('open')
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M7 17 17 7" />
            <path d="M9 7h8v8" />
            <path d="M5 5v14h14" />
        </svg>
    @break

    @default
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
        </svg>
@endswitch
