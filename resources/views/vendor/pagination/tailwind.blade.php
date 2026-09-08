{{-- Pagination lembar indeks: kotak garis tipis di atas kertas, halaman
     aktif blok merah dimensi. Diterbitkan karena tema bawaan framework
     bergaya terang dan tidak cocok dengan kosakata visual situs ini. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-wrap items-center justify-center gap-2">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                class="inline-flex h-11 w-11 items-center justify-center border border-ink-200 text-ink-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m15 18-6-6 6-6" />
                </svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
                class="inline-flex h-11 w-11 items-center justify-center border border-ink-300 bg-paper-50 text-ink-600 transition-colors hover:border-blueprint-500 hover:text-blueprint-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m15 18-6-6 6-6" />
                </svg>
            </a>
        @endif

        {{-- Numbers (hidden on the narrowest screens; prev/next stay usable) --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span aria-hidden="true" class="hidden h-11 w-11 items-center justify-center font-annotation text-sm text-ink-400 sm:inline-flex">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page"
                            class="hidden h-11 w-11 items-center justify-center bg-dimension-600 font-annotation text-sm font-bold text-paper-50 sm:inline-flex">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                            class="hidden h-11 w-11 items-center justify-center border border-ink-300 bg-paper-50 font-annotation text-sm font-bold text-ink-600 transition-colors hover:border-blueprint-500 hover:text-blueprint-700 sm:inline-flex">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        <span class="font-annotation text-xs text-ink-500 sm:hidden">
            {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
        </span>

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                class="inline-flex h-11 w-11 items-center justify-center border border-ink-300 bg-paper-50 text-ink-600 transition-colors hover:border-blueprint-500 hover:text-blueprint-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" />
                </svg>
            </a>
        @else
            <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                class="inline-flex h-11 w-11 items-center justify-center border border-ink-200 text-ink-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" />
                </svg>
            </span>
        @endif
    </nav>
@endif
