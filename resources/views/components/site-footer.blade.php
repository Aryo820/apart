@props(['popularCities' => collect()])

@php
    $support = config('support');
@endphp

{{-- Margin bawah lembar gambar: blok judul proyek, indeks lembar, dan
     skala gambar. Data yang diterima tidak berubah dari layout. --}}
<footer class="bg-paper-200 border-ink-300 border-t">
    <div class="py-16 sm:py-20 site-container">
        <div class="gap-x-8 gap-y-12 grid sm:grid-cols-2 lg:grid-cols-[1.5fr_.7fr_.7fr_.85fr_.75fr]">
            <div class="sm:col-span-2 lg:col-span-1">
                <p
                    class="font-xwide font-extrabold text-ink-900 text-5xl sm:text-6xl uppercase leading-[0.95] tracking-[0.01em]">
                    Santhosa
                </p>
                <p class="mt-3 uppercase annotation">Apartemen terkurasi · langsung dari pemilik</p>
                <p class="mt-6 max-w-sm text-ink-600 text-sm leading-6">
                    {{ $support['business_name'] }} menyewakan apartemen terkurasi di lokasi strategis, dengan
                    ketersediaan yang dicek langsung sebelum Anda memesan dan rincian biaya tanpa tambahan tersembunyi.
                </p>
            </div>

            <div>
                <h2 class="uppercase annotation annotation--strong">Navigasi</h2>
                {{-- min-h-11: tiap tautan footer tetap 44px sebagai target sentuh. --}}
                <ul class="space-y-1 mt-4 text-ink-600 text-sm">
                    <li><a href="{{ route('home') }}"
                            class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">Beranda</a>
                    </li>
                    <li><a href="{{ route('apartments.index') }}"
                            class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">Unit</a>
                    </li>
                    <li><a href="{{ route('home') }}#facilities"
                            class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">Fasilitas</a>
                    </li>
                    @auth
                        <li><a href="{{ route('bookings.index') }}"
                                class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">Booking
                                saya</a></li>
                    @else
                        <li><a href="{{ route('login') }}"
                                class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">Masuk</a>
                        </li>
                    @endauth
                </ul>
            </div>

            <div>
                <h2 class="uppercase annotation annotation--strong">Lokasi populer</h2>
                <ul class="space-y-1 mt-4 text-ink-600 text-sm">
                    @forelse($popularCities as $city)
                        <li>
                            <a href="{{ route('apartments.index', ['city' => $city]) }}"
                                class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">{{ $city }}</a>
                        </li>
                    @empty
                        <li class="pt-3">Kota akan tampil mengikuti unit aktif.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h2 class="uppercase annotation annotation--strong">Bantuan</h2>
                <ul class="space-y-1 mt-4 text-ink-600 text-sm">
                    <li>
                        <a href="mailto:{{ $support['email'] }}"
                            class="inline-flex items-center min-h-11 hover:text-blueprint-700 break-all transition-colors">
                            {{ $support['email'] }}
                        </a>
                    </li>
                    <li>
                        <a href="tel:{{ $support['phone_tel'] }}"
                            class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">
                            {{ $support['phone'] }}
                        </a>
                    </li>
                    <li class="pt-2 text-ink-500 text-xs leading-5">{{ $support['hours'] }}</li>
                </ul>
            </div>

            <div>
                <h2 class="uppercase annotation annotation--strong">Legal</h2>
                <ul class="space-y-1 mt-4 text-ink-600 text-sm">
                    <li><a href="{{ route('legal.terms') }}"
                            class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">Syarat
                            &amp; Ketentuan</a></li>
                    <li><a href="{{ route('legal.privacy') }}"
                            class="inline-flex items-center min-h-11 hover:text-blueprint-700 transition-colors">Kebijakan
                            Privasi</a></li>
                </ul>
            </div>
        </div>

        {{-- Klaim pembayaran sengaja dibatasi pada yang benar-benar terjadi di
             kode: Snap milik Midtrans yang mengambil detail pembayaran, bukan
             form di situs ini. Tidak ada klaim sertifikasi. --}}
        <div class="gap-4 border-ink-300 border-t mt-16 pt-7 flex flex-col md:flex-row md:items-start md:justify-between">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3l7 3v6c0 4.2-2.9 7.7-7 9-4.1-1.3-7-4.8-7-9V6l7-3Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="m9.5 12 1.8 1.8 3.4-3.6" />
                </svg>
                <p class="max-w-lg text-ink-600 text-xs leading-5">
                    Pembayaran diproses oleh <strong class="font-semibold text-ink-900">Midtrans</strong>. Detail
                    kartu dan kredensial pembayaran Anda dimasukkan pada halaman Midtrans, tidak pada situs ini.
                </p>
            </div>

            <p class="shrink-0 text-ink-500 text-xs">
                &copy; {{ date('Y') }} {{ $support['business_name'] }}. Semua hak dilindungi.
            </p>
        </div>
    </div>
</footer>
