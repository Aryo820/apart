@extends('layouts.app')

@section('title', $apartment->title . ' — Santhosa')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($apartment->description), 150))

@php
    $gallery = $apartment->gallery_urls;
    $extraPhotos = max(0, count($gallery) - 2);
@endphp

@section('content')
    <section class="border-b border-white/10 bg-ink-900">
        <div class="site-container py-12 sm:py-14">
            <nav class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-ink-400" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="transition-colors hover:text-gold-300">Beranda</a>
                <span aria-hidden="true">›</span>
                <a href="{{ route('apartments.index') }}" class="transition-colors hover:text-gold-300">Katalog Unit</a>
                <span aria-hidden="true">›</span>
                <span class="truncate text-ink-200" aria-current="page">{{ $apartment->title }}</span>
            </nav>

            <p class="section-eyebrow mt-6">{{ $apartment->city }}</p>
            <h1 class="mt-3 max-w-3xl font-display text-4xl font-semibold leading-[1.08] tracking-[-0.03em] text-white sm:text-5xl">
                {{ $apartment->title }}
            </h1>
            <p class="mt-4 flex items-start gap-2 text-sm leading-6 text-ink-300">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1 1 18 0Z" />
                    <circle cx="12" cy="10" r="3" stroke-width="1.8" />
                </svg>
                {{ $apartment->address }}
            </p>
        </div>
    </section>

    <section class="bg-ink-950 pt-8 sm:pt-10">
        <div class="site-container grid gap-2 md:grid-cols-3">
            <div class="relative h-[300px] overflow-hidden bg-ink-800 sm:h-[420px] md:col-span-2">
                <img src="{{ $apartment->main_image_url }}" alt="{{ $apartment->title }}" class="h-full w-full object-cover" fetchpriority="high" decoding="async">
            </div>

            <div class="grid h-[180px] grid-cols-2 gap-2 sm:h-[420px] md:grid-cols-1">
                @forelse(array_slice($gallery, 0, 2) as $index => $img)
                    <div class="relative overflow-hidden bg-ink-800">
                        <img src="{{ $img }}" alt="Foto {{ $apartment->title }} nomor {{ $index + 2 }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                        @if($loop->last && $extraPhotos > 0)
                            <span class="absolute inset-x-0 bottom-0 bg-ink-950/85 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-[0.14em] text-ivory-100">
                                +{{ $extraPhotos }} foto lainnya
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="relative col-span-2 overflow-hidden bg-ink-800 md:col-span-1">
                        <img src="{{ $apartment->main_image_url }}" alt="" class="h-full w-full object-cover opacity-40" loading="lazy" decoding="async">
                    </div>
                @endforelse
            </div>
        </div>
    </section>
    <section class="bg-ink-950 py-12 sm:py-16">
        <div class="site-container grid gap-12 lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-14">
            <div class="min-w-0 space-y-14">
                <div>
                    <h2 class="section-eyebrow">Spesifikasi unit</h2>
                    <div class="mt-5 grid grid-cols-2 border-l border-t border-white/10 sm:grid-cols-4">
                        @foreach([
                            ['label' => 'Kamar tidur', 'value' => $apartment->bedrooms . ' kamar', 'icon' => 'M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6'],
                            ['label' => 'Kamar mandi', 'value' => $apartment->bathrooms . ' kamar', 'icon' => 'M4 12h16v5a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-5Zm3-9a3 3 0 0 0-3 3v6'],
                            ['label' => 'Luas unit', 'value' => $apartment->area_sqm . ' m²', 'icon' => 'M4 8V4h4M20 8V4h-4M4 16v4h4m12-4v4h-4'],
                            ['label' => 'Kapasitas', 'value' => $apartment->capacity . ' orang', 'icon' => 'M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 4a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7Zm7.5 3.5a3 3 0 0 1 0 6M21 20v-2a4 4 0 0 0-3-3.87'],
                        ] as $spec)
                            <div class="border-b border-r border-white/10 px-4 py-5 sm:px-5">
                                <svg class="h-5 w-5 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="{{ $spec['icon'] }}" />
                                </svg>
                                <p class="mt-3 text-[10px] font-bold uppercase tracking-[0.14em] text-ink-400">{{ $spec['label'] }}</p>
                                <p class="mt-1 text-sm font-semibold text-white">{{ $spec['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h2 class="section-eyebrow">Tentang unit ini</h2>
                    <div class="mt-5 max-w-2xl text-sm leading-7 text-ink-200">
                        {!! nl2br(e($apartment->description)) !!}
                    </div>
                </div>

                @if($apartment->facilities->isNotEmpty())
                    <div>
                        <h2 class="section-eyebrow">Fasilitas unit</h2>
                        <div class="mt-5 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($apartment->facilities as $fac)
                                <div class="flex items-center gap-3 border-b border-white/8 pb-4">
                                    <span class="shrink-0 text-gold-400">
                                        <x-facility-icon :name="$fac->icon" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0 text-sm text-ivory-100">{{ $fac->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="lg:sticky lg:top-24">
                <div class="border border-white/10 bg-ink-900 p-6">
                    <p class="section-eyebrow before:hidden">Mulai dari</p>
                    <p class="mt-2 text-2xl font-semibold tracking-[-0.02em] text-ivory-100">
                        IDR {{ number_format($apartment->price_per_night, 0, ',', '.') }}
                        <span class="text-xs font-normal text-ink-400">/ malam</span>
                    </p>

                    <form action="{{ route('bookings.store') }}" method="POST" id="bookingForm" class="mt-7 space-y-4" data-submit-loading>
                        @csrf
                        <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">

                        <div>
                            <label class="search-field @error('check_in') border-rose-400/70 @enderror">
                                <span class="search-field__label">Tanggal check-in</span>
                                <input type="date" name="check_in" id="check_in" value="{{ old('check_in') }}" min="{{ date('Y-m-d') }}" required
                                    @error('check_in') aria-invalid="true" aria-describedby="check_in_error" @enderror
                                    class="search-field__control">
                            </label>
                            @error('check_in')
                                <p id="check_in_error" class="mt-1.5 text-xs leading-5 text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="search-field @error('check_out') border-rose-400/70 @enderror">
                                <span class="search-field__label">Tanggal check-out</span>
                                <input type="date" name="check_out" id="check_out" value="{{ old('check_out') }}" min="{{ date('Y-m-d', strtotime('+1 day')) }}" required
                                    @error('check_out') aria-invalid="true" aria-describedby="check_out_error" @enderror
                                    class="search-field__control">
                            </label>
                            @error('check_out')
                                <p id="check_out_error" class="mt-1.5 text-xs leading-5 text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <p id="availabilityMessage" class="hidden" role="status" aria-live="polite"></p>

                        @if($bookedDates->isNotEmpty())
                            <details class="border border-white/6 bg-ink-950/70 px-3 py-2.5">
                                <summary class="cursor-pointer text-[10px] font-bold uppercase tracking-[0.14em] text-ink-400 transition-colors hover:text-gold-300">
                                    Tanggal tidak tersedia ({{ $bookedDates->count() }} periode)
                                </summary>
                                <ul class="mt-2.5 space-y-1 text-xs leading-5 text-ink-400">
                                    @foreach($bookedDates as $range)
                                        <li class="line-through decoration-rose-400/60">
                                            {{ \Illuminate\Support\Carbon::parse($range['from'])->translatedFormat('d M Y') }}
                                            &ndash;
                                            {{ \Illuminate\Support\Carbon::parse($range['to'])->translatedFormat('d M Y') }}
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif

                        <div>
                            <label for="notes" class="search-field__label block">Catatan khusus (opsional)</label>
                            <textarea name="notes" id="notes" rows="2" placeholder="Permintaan khusus / perkiraan waktu kedatangan"
                                class="mt-2 w-full border border-white/6 bg-ink-950/70 px-3 py-2.5 text-sm text-white placeholder:text-ink-400 focus:border-gold-400/65">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1.5 text-xs leading-5 text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div id="priceCalculationCard" class="hidden space-y-2 border-t border-white/10 pt-4">
                            <div class="flex justify-between gap-4 text-xs text-ink-300">
                                <span>Tarif per malam</span>
                                <span>IDR {{ number_format($apartment->price_per_night, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-xs text-ink-300">
                                <span>Durasi menginap</span>
                                <span id="calcNights">0 malam</span>
                            </div>
                            <div class="flex justify-between gap-4 text-xs text-ink-300">
                                <span>Subtotal</span>
                                <span id="calcSubtotal">IDR 0</span>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 border-t border-white/10 pt-3">
                                <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-ink-400">Total</span>
                                <span id="calcTotalPrice" class="text-base font-semibold text-gold-400">IDR 0</span>
                            </div>
                        </div>

                        @error('payment')
                            <p class="border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-xs leading-5 text-rose-200" role="alert">{{ $message }}</p>
                        @enderror

                        @auth
                            <button type="submit" id="submitBtn" class="gold-button w-full" data-loading-label="Memproses...">
                                <span>Booking Sekarang</span>
                            </button>
                            <p class="text-center text-[10px] font-bold uppercase tracking-[0.12em] text-ink-400">
                                Belum ada penagihan pada langkah ini
                            </p>
                        @else
                            <a href="{{ route('login') }}" class="flex min-h-11 w-full items-center justify-center border border-white/15 text-[11px] font-bold uppercase tracking-[0.1em] text-white transition-colors hover:border-gold-400/50 hover:text-gold-300">
                                Masuk untuk booking
                            </a>
                            <p class="text-center text-[10px] leading-5 text-ink-400">
                                Belum punya akun? <a href="{{ route('register') }}" class="font-bold text-gold-400 transition-colors hover:text-gold-200">Daftar dulu</a>
                            </p>
                        @endauth
                    </form>
                </div>
            </div>
        </div>
    </section>

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
                const submitBtn = document.getElementById('submitBtn');
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
                    ok: 'border border-emerald-400/40 bg-emerald-500/10 px-3 py-2.5 text-xs leading-5 text-emerald-200',
                    busy: 'border border-white/10 bg-white/5 px-3 py-2.5 text-xs leading-5 text-ink-300',
                    error: 'border border-rose-400/40 bg-rose-500/10 px-3 py-2.5 text-xs leading-5 text-rose-200',
                };

                function setMessage(text, tone) {
                    message.textContent = text || '';
                    message.className = text ? TONES[tone] : 'hidden';
                }

                function setSubmitEnabled(enabled) {
                    if (submitBtn) submitBtn.disabled = !enabled;
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
                    setSubmitEnabled(false);

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
