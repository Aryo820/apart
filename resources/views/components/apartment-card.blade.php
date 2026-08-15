@props([
    'apartment',
    'priority' => false,
    // Full utility class so Tailwind can see it in source; the listing grid
    // uses a landscape crop, the home rail keeps the portrait one.
    'aspect' => 'aspect-[4/5]',
])

<article class="group min-w-0">
    <a href="{{ route('apartments.show', $apartment->slug) }}" class="relative block {{ $aspect }} overflow-hidden bg-ink-800">
        <img
            src="{{ $apartment->main_image_url }}"
            alt="{{ $apartment->title }}"
            width="720"
            height="900"
            class="h-full w-full object-cover transition duration-500 ease-out group-hover:scale-[1.025] hover-device:grayscale hover-device:group-hover:grayscale-0"
            loading="{{ $priority ? 'eager' : 'lazy' }}"
            fetchpriority="{{ $priority ? 'high' : 'auto' }}"
            decoding="async"
        >
        @if($apartment->is_featured)
            <span class="absolute left-3 top-3 bg-gold-400 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[0.12em] text-ink-950">
                Pilihan
            </span>
        @endif
        <span class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-ink-950/80 to-transparent" aria-hidden="true"></span>
    </a>

    <div class="pt-5">
        <p class="truncate text-[10px] font-bold uppercase tracking-[0.16em] text-ink-400">
            {{ $apartment->city }} · {{ $apartment->address }}
        </p>
        <h3 class="mt-2 font-display text-xl font-semibold leading-snug text-white">
            <a href="{{ route('apartments.show', $apartment->slug) }}" class="transition-colors hover:text-gold-300">
                {{ $apartment->title }}
            </a>
        </h3>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-ink-300">
            <span>{{ $apartment->bedrooms }} kamar</span>
            <span aria-hidden="true">•</span>
            <span>{{ $apartment->bathrooms }} k. mandi</span>
            <span aria-hidden="true">•</span>
            <span>{{ $apartment->capacity }} tamu</span>
            <span aria-hidden="true">•</span>
            <span>{{ $apartment->area_sqm }} m²</span>
        </div>

        <div class="mt-4 flex items-end justify-between gap-4">
            <p class="text-sm font-semibold text-ivory-100">
                IDR {{ number_format($apartment->price_per_night, 0, ',', '.') }}
                <span class="text-[10px] font-normal text-ink-400">/ malam</span>
            </p>
            <a href="{{ route('apartments.show', $apartment->slug) }}" class="inline-flex min-h-11 items-center gap-1 text-[10px] font-bold uppercase tracking-[0.12em] text-gold-400 transition-colors hover:text-gold-200">
                Detail
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" />
                </svg>
            </a>
        </div>
    </div>
</article>
