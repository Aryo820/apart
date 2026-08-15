@props(['popularCities' => collect()])

<footer class="border-t border-white/10 bg-ink-900">
    <div class="site-container py-16 sm:py-20">
        <div class="grid gap-14 lg:grid-cols-[1.45fr_.65fr_.65fr]">
            <div>
                <p class="max-w-md font-display text-5xl font-semibold uppercase leading-[0.92] tracking-[-0.035em] text-ivory-100 sm:text-6xl">
                    Mulai<br>
                    <span class="text-gold-400">Perjalanan</span><br>
                    Anda
                </p>
            </div>

            <div>
                <h2 class="section-eyebrow before:hidden">Navigasi</h2>
                {{-- min-h-11: tiap tautan footer harus tetap 44px sebagai target
                     sentuh, jadi jarak antar-item dikecilkan agar tinggi kolom
                     tidak berubah jauh. --}}
                <ul class="mt-4 space-y-1 text-sm text-ink-300">
                    <li><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center transition-colors hover:text-white">Beranda</a></li>
                    <li><a href="{{ route('apartments.index') }}" class="inline-flex min-h-11 items-center transition-colors hover:text-white">Unit</a></li>
                    <li><a href="{{ route('home') }}#facilities" class="inline-flex min-h-11 items-center transition-colors hover:text-white">Fasilitas</a></li>
                </ul>
            </div>

            <div>
                <h2 class="section-eyebrow before:hidden">Lokasi populer</h2>
                <ul class="mt-4 space-y-1 text-sm text-ink-300">
                    @forelse($popularCities as $city)
                        <li>
                            <a href="{{ route('apartments.index', ['city' => $city]) }}" class="inline-flex min-h-11 items-center transition-colors hover:text-white">{{ $city }}</a>
                        </li>
                    @empty
                        <li class="pt-3">Kota akan tampil mengikuti unit aktif.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="mt-16 flex flex-col gap-4 border-t border-white/10 pt-7 text-xs text-ink-400 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} Santhosa. Semua hak dilindungi.</p>
            <p>Reservasi apartemen premium yang aman dan transparan.</p>
        </div>
    </div>
</footer>
