{{--
    Ink/gold pagination. Published because the framework default is styled for
    a light theme and only goes dark via `dark:` variants (prefers-color-scheme),
    so it rendered as a white bar with a blue focus ring on this always-dark UI.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-wrap items-center justify-center gap-2">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                class="inline-flex h-11 w-11 items-center justify-center border border-white/10 text-ink-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m15 18-6-6 6-6" />
                </svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
                class="inline-flex h-11 w-11 items-center justify-center border border-white/15 text-ink-200 transition-colors hover:border-gold-400/60 hover:text-gold-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m15 18-6-6 6-6" />
                </svg>
            </a>
        @endif

        {{-- Numbers (hidden on the narrowest screens; prev/next stay usable) --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span aria-hidden="true" class="hidden h-11 w-11 items-center justify-center text-xs text-ink-400 sm:inline-flex">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page"
                            class="hidden h-11 w-11 items-center justify-center bg-gold-400 text-xs font-extrabold text-ink-950 sm:inline-flex">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                            class="hidden h-11 w-11 items-center justify-center border border-white/15 text-xs font-bold text-ink-200 transition-colors hover:border-gold-400/60 hover:text-gold-300 sm:inline-flex">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        <span class="text-xs font-bold uppercase tracking-[0.14em] text-ink-400 sm:hidden">
            {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
        </span>

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                class="inline-flex h-11 w-11 items-center justify-center border border-white/15 text-ink-200 transition-colors hover:border-gold-400/60 hover:text-gold-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" />
                </svg>
            </a>
        @else
            <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                class="inline-flex h-11 w-11 items-center justify-center border border-white/10 text-ink-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" />
                </svg>
            </span>
        @endif
    </nav>
@endif
