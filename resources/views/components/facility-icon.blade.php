@props(['name' => 'sparkles'])

@php
    $normalizedName = strtolower((string) $name);
    $iconClass = $attributes->get('class', 'h-6 w-6');
@endphp

@switch($normalizedName)
    @case('wifi')
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12.55a11 11 0 0 1 14.08 0M8.53 16.1a6 6 0 0 1 6.95 0M12 20h.01M1.42 9a16 16 0 0 1 21.16 0" />
        </svg>
        @break

    @case('pool')
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2 17c1.5 0 1.5 1 3 1s1.5-1 3-1 1.5 1 3 1 1.5-1 3-1 1.5 1 3 1 1.5-1 3-1M2 21c1.5 0 1.5 1 3 1s1.5-1 3-1 1.5 1 3 1 1.5-1 3-1 1.5 1 3 1 1.5-1 3-1M7 14l3.5-6 3 2L17 4" />
        </svg>
        @break

    @case('gym')
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 7v10M3.5 9v6M18 7v10m2.5-8v6M6 12h12" />
        </svg>
        @break

    @case('tv')
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <rect width="18" height="13" x="3" y="5" rx="2" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-width="1.8" d="m8 2 4 3 4-3M9 21h6" />
        </svg>
        @break

    @case('shield')
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 12 2 2 4-4" />
        </svg>
        @break

    @case('parking')
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke-width="1.8" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 17V7h3a3 3 0 0 1 0 6h-3" />
        </svg>
        @break

    @case('kitchen')
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 3v7a3 3 0 0 0 3 3h1M7 3v18M15 3v7a3 3 0 0 0 3 3h1M18 3v18M4 7h6" />
        </svg>
        @break

    @case('balcony')
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 21V4h16v17M4 15h16M8 15v6m4-6v6m4-6v6M8 4v11m8-11v11" />
        </svg>
        @break

    @default
        <svg class="{{ $iconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m12 3-1.6 4.4L6 9l4.4 1.6L12 15l1.6-4.4L18 9l-4.4-1.6L12 3ZM5 15l-.8 2.2L2 18l2.2.8L5 21l.8-2.2L8 18l-2.2-.8L5 15Zm14-2-1.1 2.9L15 17l2.9 1.1L19 21l1.1-2.9L23 17l-2.9-1.1L19 13Z" />
        </svg>
@endswitch
