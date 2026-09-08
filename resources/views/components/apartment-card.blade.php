@props([
    'apartment',
    'priority' => false,
    // Full utility class so Tailwind can see it in source; the listing grid
    // uses a landscape crop, the home rail keeps the portrait one.
    'aspect' => 'aspect-[4/5]',
])

<article class="group min-w-0">
    <div class="plate {{ $aspect }}">
        <a href="{{ route('apartments.show', $apartment->slug) }}" class="relative block h-full w-full overflow-hidden bg-paper-300">
            <img
                src="{{ $apartment->display_image_url }}"
                alt="{{ $apartment->title }}"
                width="720"
                height="900"
                class="h-full w-full object-cover transition duration-500 ease-out group-hover:scale-[1.025]"
                loading="{{ $priority ? 'eager' : 'lazy' }}"
                fetchpriority="{{ $priority ? 'high' : 'auto' }}"
                decoding="async"
            >
            @if($apartment->is_featured)
                <span class="stamp absolute left-4 top-4 border-dimension-600 bg-paper-50 text-dimension-600">
                    Pilihan
                </span>
            @endif
        </a>
    </div>

    <div class="pt-4">
        <p class="annotation truncate uppercase">
            {{ $apartment->city }} · {{ $apartment->address }}
        </p>
        <h3 class="mt-2 text-xl font-bold leading-snug tracking-[-0.015em] text-ink-900">
            <a href="{{ route('apartments.show', $apartment->slug) }}" class="transition-colors hover:text-blueprint-700">
                {{ $apartment->title }}
            </a>
        </h3>

        <p class="mt-2.5 font-annotation text-xs text-ink-600">
            {{ $apartment->bedrooms }} kt · {{ $apartment->bathrooms }} km · {{ $apartment->capacity }} tamu · {{ $apartment->area_sqm }} m²
        </p>

        {{-- Fasilitas dibatasi 3 supaya kartu tetap ringkas. Relasinya sudah
             di-eager-load HomeController dan ApartmentController::index, jadi
             tidak ada query tambahan per kartu. relationLoaded() menjaga kartu
             tetap aman kalau nanti dipakai dari query tanpa with('facilities'). --}}
        @if($apartment->relationLoaded('facilities') && $apartment->facilities->isNotEmpty())
            @php $shown = $apartment->facilities->take(3); @endphp
            <ul class="mt-3 flex flex-wrap items-center gap-1.5">
                @foreach($shown as $facility)
                    <li class="inline-flex items-center gap-1.5 border border-ink-200 bg-paper-50 px-2 py-1 text-xs text-ink-600">
                        <span class="text-blueprint-600">
                            <x-facility-icon :name="$facility->icon" class="h-3.5 w-3.5" />
                        </span>
                        {{ $facility->name }}
                    </li>
                @endforeach
                @if($apartment->facilities->count() > $shown->count())
                    <li class="px-1 font-annotation text-xs text-ink-500">
                        +{{ $apartment->facilities->count() - $shown->count() }} lainnya
                    </li>
                @endif
            </ul>
        @endif

        <div class="mt-4 flex items-end justify-between gap-4 border-t border-ink-200 pt-4">
            <p class="font-annotation text-sm font-bold text-ink-900">
                IDR {{ number_format($apartment->price_per_night, 0, ',', '.') }}
                <span class="font-normal text-ink-500">/ malam</span>
            </p>
            <a href="{{ route('apartments.show', $apartment->slug) }}" class="inline-flex min-h-11 items-center gap-1 font-annotation text-xs font-bold uppercase tracking-[0.08em] text-dimension-600 transition-colors hover:text-dimension-700">
                Detail
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" />
                </svg>
            </a>
        </div>
    </div>
</article>
