@props(['popularCities' => collect()])

@php
    $support = config('support');
@endphp

<footer class="border-t border-white/10 bg-ink-900">
    <div class="site-container py-16 sm:py-20">
        <div class="grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-[1.5fr_.7fr_.7fr_.85fr_.75fr]">
            <div class="sm:col-span-2 lg:col-span-1">
                <p class="max-w-md font-display text-5xl font-semibold uppercase leading-[0.92] tracking-[-0.035em] text-ivory-100 sm:text-6xl">
                    Mulai<br>
                    <span class="text-gold-400">Perjalanan</span><br>
                    Anda
                </p>
                <p class="mt-6 max-w-sm text-sm leading-6 text-ink-300">
                    {{ $support['business_name'] }} menyewakan apartemen terkurasi di lokasi strategis, dengan
                    ketersediaan yang dicek langsung sebelum Anda memesan dan rincian biaya tanpa tambahan tersembunyi.
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
                    @auth
                        <li><a href="{{ route('bookings.index') }}" class="inline-flex min-h-11 items-center transition-colors hover:text-white">Booking saya</a></li>
                    @else
                        <li><a href="{{ route('login') }}" class="inline-flex min-h-11 items-center transition-colors hover:text-white">Masuk</a></li>
                    @endauth
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

            <div>
                <h2 class="section-eyebrow before:hidden">Bantuan</h2>
                <ul class="mt-4 space-y-1 text-sm text-ink-300">
                    <li>
                        <a href="mailto:{{ $support['email'] }}" class="inline-flex min-h-11 items-center break-all transition-colors hover:text-white">
                            {{ $support['email'] }}
                        </a>
                    </li>
                    <li>
                        <a href="tel:{{ $support['phone_tel'] }}" class="inline-flex min-h-11 items-center transition-colors hover:text-white">
                            {{ $support['phone'] }}
                        </a>
                    </li>
                    <li class="pt-2 text-xs leading-5 text-ink-400">{{ $support['hours'] }}</li>
                </ul>
            </div>

            <div>
                <h2 class="section-eyebrow before:hidden">Legal</h2>
                <ul class="mt-4 space-y-1 text-sm text-ink-300">
                    <li><a href="{{ route('legal.terms') }}" class="inline-flex min-h-11 items-center transition-colors hover:text-white">Syarat &amp; Ketentuan</a></li>
                    <li><a href="{{ route('legal.privacy') }}" class="inline-flex min-h-11 items-center transition-colors hover:text-white">Kebijakan Privasi</a></li>
                </ul>
            </div>
        </div>

        {{-- Klaim pembayaran sengaja dibatasi pada yang benar-benar terjadi di
             kode: Snap milik Midtrans yang mengambil detail pembayaran, bukan
             form di situs ini. Tidak ada klaim sertifikasi. --}}
        <div class="mt-16 flex flex-col gap-4 border-t border-white/10 pt-7 md:flex-row md:items-start md:justify-between">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3l7 3v6c0 4.2-2.9 7.7-7 9-4.1-1.3-7-4.8-7-9V6l7-3Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="m9.5 12 1.8 1.8 3.4-3.6" />
                </svg>
                <p class="max-w-lg text-xs leading-5 text-ink-300">
                    Pembayaran diproses oleh <strong class="font-semibold text-ivory-100">Midtrans</strong>. Detail
                    kartu dan kredensial pembayaran Anda dimasukkan pada halaman Midtrans, tidak pada situs ini.
                </p>
            </div>

            <p class="shrink-0 text-xs text-ink-400">
                &copy; {{ date('Y') }} {{ $support['business_name'] }}. Semua hak dilindungi.
            </p>
        </div>
    </div>
</footer>
