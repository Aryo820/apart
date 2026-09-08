@extends('layouts.app')

@section('title', $apartment->title . ' — Santhosa')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($apartment->description), 150))

@php
    $gallery = $apartment->gallery_urls;
    $extraPhotos = max(0, count($gallery) - 2);
@endphp

@section('content')
    <section class="border-b border-ink-300 bg-paper-200">
        <div class="site-container py-12 sm:py-14">
            <nav class="annotation flex items-center gap-2 uppercase" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="transition-colors hover:text-blueprint-700">Beranda</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('apartments.index') }}" class="transition-colors hover:text-blueprint-700">Katalog unit</a>
                <span aria-hidden="true">/</span>
                <span class="truncate text-ink-700" aria-current="page">{{ $apartment->title }}</span>
            </nav>

            <h1 class="mt-6 max-w-3xl text-4xl font-bold leading-[1.05] tracking-[-0.02em] text-ink-900 sm:text-5xl">
                {{ $apartment->title }}
            </h1>
            <p class="mt-4 flex items-start gap-2 font-annotation text-sm text-ink-600">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1 1 18 0Z" />
                    <circle cx="12" cy="10" r="3" stroke-width="1.8" />
                </svg>
                {{ $apartment->address }}, {{ $apartment->city }}
            </p>
        </div>
    </section>

    {{-- Plat galeri: foto utama + dua plat samping, sisa foto ditandai arsiran. --}}
    <section class="bg-paper-100 pt-8 sm:pt-10">
        <div class="site-container grid gap-3 md:grid-cols-3">
            <div class="plate md:col-span-2">
                <div class="relative h-[280px] overflow-hidden bg-paper-300 sm:h-[420px]">
                    <img src="{{ $apartment->display_image_url }}" alt="{{ $apartment->title }}" class="h-full w-full object-cover" fetchpriority="high" decoding="async">
                </div>
            </div>

            <div class="grid gap-3 md:h-full md:grid-rows-2">
                @forelse(array_slice($gallery, 0, 2) as $index => $img)
                    <div class="plate h-[160px] sm:h-[204px]">
                        <div class="relative h-full w-full overflow-hidden bg-paper-300">
                            <img src="{{ $img }}" alt="Foto {{ $apartment->title }} nomor {{ $index + 2 }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                            @if($loop->last && $extraPhotos > 0)
                                <span class="absolute inset-0 flex items-center justify-center bg-paper-50/85 font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-800 hatch">
                                    +{{ $extraPhotos }} foto lainnya
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="plate h-[160px] sm:h-[204px] md:row-span-2">
                        <div class="h-full w-full overflow-hidden bg-paper-300">
                            <img src="{{ $apartment->display_image_url }}" alt="" class="h-full w-full object-cover opacity-40" loading="lazy" decoding="async">
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Ruang untuk bar CTA mobile diberikan sekali di body (app.css), bukan
         lewat padding besar di section ini — kalau di sini, footer-nya yang
         tertutup. --}}
    <section class="bg-paper-100 pb-16 pt-12 sm:pt-16">
        <div class="site-container grid gap-12 lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-14">
            <div class="min-w-0 space-y-14">
                <div>
                    <h2 class="text-2xl font-bold tracking-[-0.015em] text-ink-900">Spesifikasi unit</h2>
                    {{-- Tabel spesifikasi: grid garis tipis seperti schedule
                         material di lembar gambar. --}}
                    <div class="mt-5 grid grid-cols-2 border-l border-t border-ink-300 sm:grid-cols-4">
                        @foreach([
                            ['label' => 'Kamar tidur', 'value' => $apartment->bedrooms . ' kamar', 'icon' => 'M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6'],
                            ['label' => 'Kamar mandi', 'value' => $apartment->bathrooms . ' kamar', 'icon' => 'M4 12h16v5a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-5Zm3-9a3 3 0 0 0-3 3v6'],
                            ['label' => 'Luas unit', 'value' => $apartment->area_sqm . ' m²', 'icon' => 'M4 8V4h4M20 8V4h-4M4 16v4h4m12-4v4h-4'],
                            ['label' => 'Kapasitas', 'value' => $apartment->capacity . ' orang', 'icon' => 'M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 4a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7Zm7.5 3.5a3 3 0 0 1 0 6M21 20v-2a4 4 0 0 0-3-3.87'],
                        ] as $spec)
                            <div class="border-b border-r border-ink-300 bg-paper-50 px-4 py-5 sm:px-5">
                                <svg class="h-5 w-5 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="{{ $spec['icon'] }}" />
                                </svg>
                                <p class="annotation mt-3 uppercase">{{ $spec['label'] }}</p>
                                <p class="mt-1 font-annotation text-sm font-bold text-ink-900">{{ $spec['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h2 class="text-2xl font-bold tracking-[-0.015em] text-ink-900">Tentang unit ini</h2>
                    {{-- Deskripsi panjang dibatasi tingginya, bukan dipotong
                         isinya: teks lengkap tetap ada di DOM. Togglenya
                         <details> native, jadi bisa dioperasikan keyboard tanpa
                         JS. Ambang 700 karakter ≈ 10 baris pada kolom ini. --}}
                    @php $descriptionIsLong = mb_strlen((string) $apartment->description) > 700; @endphp
                    <div class="mt-5 max-w-2xl text-[0.9375rem] leading-7 text-ink-700 {{ $descriptionIsLong ? 'prose-clamp' : '' }}">
                        {!! nl2br(e($apartment->description)) !!}
                    </div>
                    @if($descriptionIsLong)
                        <details class="prose-reveal">
                            <summary>
                                <span class="prose-reveal__more">Baca selengkapnya</span>
                                <span class="prose-reveal__less">Tutup deskripsi</span>
                            </summary>
                        </details>
                    @endif
                </div>

                @if($apartment->facilities->isNotEmpty())
                    <div>
                        <h2 class="text-2xl font-bold tracking-[-0.015em] text-ink-900">Fasilitas unit</h2>
                        <ul class="mt-5 grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($apartment->facilities as $fac)
                                <li class="flex items-center gap-3 border-b border-ink-200 py-3.5">
                                    <span class="shrink-0 text-blueprint-600">
                                        <x-facility-icon :name="$fac->icon" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0 text-sm text-ink-800">{{ $fac->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Formulir reservasi: panel teknis sticky dengan judul blok,
                 harga mono, dan kalkulasi yang dihitung ulang langsung. --}}
            <div class="lg:sticky lg:top-24">
                <div class="border border-ink-300 bg-paper-50 shadow-[0_16px_40px_-24px_rgba(23,26,21,.3)]">
                    <div class="flex items-center justify-between border-b border-ink-300 bg-paper-200 px-5 py-3">
                        <h2 class="font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-800">Formulir reservasi</h2>
                        <span class="annotation uppercase">FR-{{ str_pad((string) $apartment->id, 3, '0', STR_PAD_LEFT) }}</span>
                    </div>

                    <div class="p-5">
                        <p class="annotation uppercase">Tarif per malam</p>
                        <p class="mt-1.5 font-annotation text-2xl font-bold tracking-[-0.01em] text-ink-900">
                            IDR {{ number_format($apartment->price_per_night, 0, ',', '.') }}
                            <span class="text-xs font-normal text-ink-500">/ malam</span>
                        </p>

                        <form action="{{ route('bookings.store') }}" method="POST" id="bookingForm" class="mt-6 space-y-4" data-submit-loading>
                            @csrf
                            <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">

                            <div>
                                <label class="field @error('check_in') field--error @enderror">
                                    <span class="field__label">Tanggal check-in</span>
                                    <input type="date" name="check_in" id="check_in" value="{{ old('check_in') }}" min="{{ date('Y-m-d') }}" required
                                        @error('check_in') aria-invalid="true" aria-describedby="check_in_error" @enderror
                                        class="field__control">
                                </label>
                                @error('check_in')
                                    <p id="check_in_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="field @error('check_out') field--error @enderror">
                                    <span class="field__label">Tanggal check-out</span>
                                    <input type="date" name="check_out" id="check_out" value="{{ old('check_out') }}" min="{{ date('Y-m-d', strtotime('+1 day')) }}" required
                                        @error('check_out') aria-invalid="true" aria-describedby="check_out_error" @enderror
                                        class="field__control">
                                </label>
                                @error('check_out')
                                    <p id="check_out_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <p id="availabilityMessage" class="hidden" role="status" aria-live="polite"></p>

                            @if($bookedDates->isNotEmpty())
                                {{-- Tanggal terisi digambar arsir — kekosongan kalender
                                     jadi bagian sistem, bukan catatan kaki. --}}
                                <details class="border border-ink-200 bg-paper-100 px-3 py-2.5">
                                    <summary class="cursor-pointer font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-600 transition-colors hover:text-blueprint-700">
                                        Tanggal tidak tersedia ({{ $bookedDates->count() }} periode)
                                    </summary>
                                    <ul class="mt-2.5 space-y-1.5">
                                        @foreach($bookedDates as $range)
                                            <li class="hatch px-2 py-1 font-annotation text-xs leading-5 text-ink-600">
                                                {{ \Illuminate\Support\Carbon::parse($range['from'])->translatedFormat('d M Y') }}
                                                &ndash;
                                                {{ \Illuminate\Support\Carbon::parse($range['to'])->translatedFormat('d M Y') }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif

                            <div>
                                <label for="notes" class="field__label block">Catatan khusus (opsional)</label>
                                <textarea name="notes" id="notes" rows="2" placeholder="Permintaan khusus / perkiraan waktu kedatangan"
                                    class="mt-2 w-full border border-ink-300 bg-paper-100 px-3 py-2.5 font-annotation text-sm text-ink-900 placeholder:text-ink-400 focus:border-blueprint-500">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <div id="priceCalculationCard" class="hidden space-y-2 border-t border-ink-300 pt-4">
                                <div class="flex justify-between gap-4 font-annotation text-xs text-ink-600">
                                    <span>Tarif per malam</span>
                                    <span>IDR {{ number_format($apartment->price_per_night, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between gap-4 font-annotation text-xs text-ink-600">
                                    <span>Durasi menginap</span>
                                    <span id="calcNights">0 malam</span>
                                </div>
                                <div class="flex justify-between gap-4 font-annotation text-xs text-ink-600">
                                    <span>Subtotal</span>
                                    <span id="calcSubtotal">IDR 0</span>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 border-t border-ink-300 pt-3">
                                    <span class="annotation font-bold uppercase">Total</span>
                                    <span id="calcTotalPrice" class="font-annotation text-base font-bold text-dimension-600">IDR 0</span>
                                </div>
                            </div>

                            @error('payment')
                                <p class="border border-dimension-500 bg-dimension-50 px-4 py-3 text-xs leading-5 text-dimension-700" role="alert">{{ $message }}</p>
                            @enderror

                            @auth
                                <button type="submit" id="submitBtn" class="btn-primary w-full" data-loading-label="Memproses...">
                                    <span>Booking Sekarang</span>
                                </button>
                                <p class="text-center font-annotation text-xs font-bold uppercase tracking-[0.06em] text-ink-500">
                                    Belum ada penagihan pada langkah ini
                                </p>
                                {{-- Penyebutan Midtrans dibatasi pada faktanya: gateway-nya
                                     yang mengambil detail pembayaran di langkah berikutnya. --}}
                                <p class="text-center text-xs leading-5 text-ink-500">
                                    Anda akan meninjau ringkasan reservasi lebih dulu, lalu membayar melalui Midtrans.
                                </p>
                            @else
                                <a href="{{ route('login') }}" class="btn-secondary w-full">
                                    Masuk untuk booking
                                </a>
                                <p class="text-center text-xs leading-5 text-ink-500">
                                    Belum punya akun? <a href="{{ route('register') }}" class="font-bold text-blueprint-700 transition-colors hover:text-blueprint-500">Daftar dulu</a>
                                </p>
                            @endauth
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA mobile. Di bawah lg saja: panel booking di sidebar sticky hanya
         bekerja di layar lebar, sementara di mobile ia jatuh di bawah seluruh
         deskripsi dan daftar fasilitas.

         form="bookingForm" adalah asosiasi form native — tombol ini men-submit
         form yang SAMA, jadi tidak ada logika booking kedua. Kalau tanggal
         belum diisi, validasi HTML bawaan yang menggeser fokus ke field yang
         kosong, sehingga tombol ini juga berfungsi sebagai "bawa saya ke form".
         Status disabled-nya ikut diatur pengecekan ketersediaan di bawah. --}}
    <div class="mobile-cta-bar" data-no-print>
        <div class="min-w-0">
            <p class="annotation uppercase">Mulai dari</p>
            <p class="mt-0.5 truncate font-annotation text-sm font-bold text-ink-900">
                IDR {{ number_format($apartment->price_per_night, 0, ',', '.') }}
                <span class="font-normal text-ink-500">/ malam</span>
            </p>
        </div>

        @auth
            <button type="submit" form="bookingForm" class="btn-primary shrink-0" data-loading-label="Memproses...">
                <span data-mobile-cta-label>Booking</span>
            </button>
        @else
            <a href="{{ route('login') }}" class="btn-primary shrink-0">Masuk untuk booking</a>
        @endauth
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('bookingForm');
                const checkInInput = document.getElementById('check_in');
                const checkOutInput = document.getElementById('check_out');
                const calcCard = document.getElementById('priceCalculationCard');
                const calcNights = document.getElementById('calcNights');
                const calcSubtotal = document.getElementById('calcSubtotal');
                const calcTotalPrice = document.getElementById('calcTotalPrice');
                const message = document.getElementById('availabilityMessage');
                // Semua tombol submit milik form ini — termasuk yang di luar form
                // lewat atribut form="bookingForm" (bar CTA mobile). Properti
                // .form mengembalikan form pemilik untuk kedua cara asosiasi.
                const submitButtons = Array.from(document.querySelectorAll('button[type="submit"]'))
                    .filter((button) => button.form === form);
                // Label tombol di bar CTA mobile. Pesan alasannya ada di dalam
                // form, yang di mobile berada di luar layar saat bar terlihat —
                // jadi tombol mati yang tetap berbunyi "Booking" menyesatkan.
                const ctaLabel = document.querySelector('[data-mobile-cta-label]');
                const pricePerNight = {{ $apartment->price_per_night }};
                // Rentang terpesan (pending + confirmed) dari ApartmentController::show.
                const bookedRanges = @json($bookedDates);
                const availabilityUrl = @json(route('apartments.availability', $apartment->id));

                const idr = (value) => 'IDR ' + value.toLocaleString('id-ID');

                // Aturan sama dengan Booking::scopeConflicting: check-out di hari
                // yang sama dengan check-in tamu lain masih boleh.
                const overlapsBooked = (checkIn, checkOut) =>
                    bookedRanges.some((range) => range.from < checkOut && range.to > checkIn);

                const TONES = {
                    ok: 'border border-emerald-700/40 bg-emerald-50 px-3 py-2.5 text-xs leading-5 text-emerald-800',
                    busy: 'border border-ink-300 bg-paper-50 px-3 py-2.5 text-xs leading-5 text-ink-600',
                    error: 'border border-dimension-500 bg-dimension-50 px-3 py-2.5 text-xs leading-5 text-dimension-700',
                };

                function setMessage(text, tone) {
                    message.textContent = text || '';
                    message.className = text ? TONES[tone] : 'hidden';
                }

                function setSubmitEnabled(enabled, disabledLabel = 'Tidak tersedia') {
                    submitButtons.forEach((button) => {
                        button.disabled = !enabled;
                    });

                    if (ctaLabel) {
                        ctaLabel.textContent = enabled ? 'Booking' : disabledLabel;
                    }
                }

                function calculate() {
                    const checkIn = checkInInput.value;
                    const checkOut = checkOutInput.value;

                    if (!checkIn || !checkOut || checkOut <= checkIn) {
                        calcCard.classList.add('hidden');
                        return 0;
                    }

                    const nights = Math.round((new Date(checkOut) - new Date(checkIn)) / 86400000);
                    const subtotal = nights * pricePerNight;

                    calcNights.textContent = nights + ' malam';
                    calcSubtotal.textContent = idr(subtotal);
                    calcTotalPrice.textContent = idr(subtotal);
                    calcCard.classList.remove('hidden');

                    return nights;
                }

                let requestId = 0;

                async function verify() {
                    const checkIn = checkInInput.value;
                    const checkOut = checkOutInput.value;
                    const current = ++requestId;

                    if (!calculate()) {
                        setMessage('');
                        setSubmitEnabled(true);
                        return;
                    }

                    if (overlapsBooked(checkIn, checkOut)) {
                        setMessage('Tanggal tersebut sudah dipesan. Silakan pilih tanggal lain.', 'error');
                        setSubmitEnabled(false);
                        return;
                    }

                    setMessage('Memeriksa ketersediaan...', 'busy');
                    setSubmitEnabled(false, 'Memeriksa...');

                    try {
                        const response = await fetch(availabilityUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                            },
                            body: JSON.stringify({ check_in: checkIn, check_out: checkOut }),
                        });

                        if (current !== requestId) return; // balasan basi, tanggal sudah diganti

                        if (!response.ok) {
                            throw new Error('availability check failed');
                        }

                        const data = await response.json();
                        setMessage(data.message, data.available ? 'ok' : 'error');
                        setSubmitEnabled(data.available);
                    } catch (error) {
                        if (current !== requestId) return;
                        // Endpoint tak terjangkau — jangan kunci tamu. Server tetap
                        // memeriksa ulang konflik di dalam transaksi booking.
                        setMessage('');
                        setSubmitEnabled(true);
                    }
                }

                let debounce;
                const scheduleVerify = () => {
                    clearTimeout(debounce);
                    debounce = setTimeout(verify, 250);
                };

                checkInInput.addEventListener('change', function () {
                    if (checkInInput.value) {
                        const nextDay = new Date(checkInInput.value);
                        nextDay.setDate(nextDay.getDate() + 1);
                        checkOutInput.min = nextDay.toISOString().split('T')[0];
                        if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
                            checkOutInput.value = nextDay.toISOString().split('T')[0];
                        }
                    }
                    scheduleVerify();
                });

                checkOutInput.addEventListener('change', scheduleVerify);

                // Tanggal bisa sudah terisi dari old() setelah validasi gagal —
                // hitung ulang sekali supaya rincian total tidak ikut hilang.
                calculate();
            });
        </script>
    @endpush
@endsection
