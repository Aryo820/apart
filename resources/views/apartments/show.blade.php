@extends('layouts.app')

@section('title', $apartment->title . ' - ApartStay')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-6">
        <a href="{{ route('home') }}" class="hover:text-white">Beranda</a>
        <span>/</span>
        <a href="{{ route('apartments.index') }}" class="hover:text-white">Apartemen</a>
        <span>/</span>
        <span class="text-slate-200 font-semibold truncate">{{ $apartment->title }}</span>
    </nav>

    <!-- Title Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-6">
        <div>
            <span class="px-2.5 py-1 text-xs font-bold bg-brand-500/20 text-brand-400 border border-brand-500/30 rounded-md inline-block mb-2">
                {{ $apartment->city }}
            </span>
            <h1 class="text-2xl sm:text-4xl font-extrabold text-white tracking-tight">{{ $apartment->title }}</h1>
            <p class="text-sm text-slate-400 flex items-center gap-1 mt-1">
                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                {{ $apartment->address }}
            </p>
        </div>

        <div class="bg-slate-800/90 border border-slate-700 px-5 py-3 rounded-2xl flex items-center gap-3">
            <span class="text-xs text-slate-400">Harga Per Malam</span>
            <span class="text-2xl font-extrabold text-brand-400">Rp {{ number_format($apartment->price_per_night, 0, ',', '.') }}</span>
        </div>
    </div>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10 rounded-2xl overflow-hidden">
        <div class="md:col-span-2 h-[340px] sm:h-[440px] relative">
            <img src="{{ $apartment->main_image }}" alt="{{ $apartment->title }}" class="w-full h-full object-cover">
        </div>
        <div class="grid grid-cols-2 md:grid-cols-1 gap-4 h-[340px] sm:h-[440px]">
            @if(!empty($apartment->images) && is_array($apartment->images))
                @foreach(array_slice($apartment->images, 0, 2) as $img)
                    <div class="h-full relative overflow-hidden rounded-xl">
                        <img src="{{ $img }}" alt="Gallery" class="w-full h-full object-cover">
                    </div>
                @endforeach
            @else
                <div class="h-full relative overflow-hidden rounded-xl bg-slate-800">
                    <img src="{{ $apartment->main_image }}" alt="Gallery" class="w-full h-full object-cover opacity-60">
                </div>
            @endif
        </div>
    </div>

    <!-- Content Layout: Left Details, Right Booking Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-10">
            <!-- Specifications -->
            <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                <div>
                    <span class="text-xs text-slate-400 block mb-1">Kamar Tidur</span>
                    <span class="text-lg font-bold text-white flex items-center justify-center gap-1">
                        <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        {{ $apartment->bedrooms }} Room
                    </span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block mb-1">Kamar Mandi</span>
                    <span class="text-lg font-bold text-white flex items-center justify-center gap-1">
                        <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                        {{ $apartment->bathrooms }} Bath
                    </span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block mb-1">Luas Unit</span>
                    <span class="text-lg font-bold text-white flex items-center justify-center gap-1">
                        <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-2V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        {{ $apartment->area_sqm }} m²
                    </span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block mb-1">Kapasitas Maks</span>
                    <span class="text-lg font-bold text-white flex items-center justify-center gap-1">
                        <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        {{ $apartment->capacity }} Guests
                    </span>
                </div>
            </div>

            <!-- Description -->
            <div>
                <h3 class="text-xl font-bold text-white mb-3">Deskripsi Apartemen</h3>
                <div class="prose prose-invert max-w-none text-slate-300 leading-relaxed text-sm">
                    {!! nl2br(e($apartment->description)) !!}
                </div>
            </div>

            <!-- Facilities -->
            <div>
                <h3 class="text-xl font-bold text-white mb-4">Fasilitas Unit</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    @foreach($apartment->facilities as $fac)
                        <div class="flex items-center gap-3 bg-slate-800/60 border border-slate-700/60 p-3.5 rounded-xl">
                            <div class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-400 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-sm font-semibold text-white">{{ $fac->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Right Column: Interactive Booking Widget -->
        <div class="lg:col-span-1">
            <div class="bg-slate-800/90 border border-slate-700 rounded-2xl p-6 sticky top-28 shadow-2xl">
                <h3 class="text-xl font-bold text-white mb-1">Form Pemesanan</h3>
                <p class="text-xs text-slate-400 mb-6">Pilih tanggal check-in & check-out untuk reservasi.</p>

                <form action="{{ route('bookings.store') }}" method="POST" id="bookingForm" class="space-y-4">
                    @csrf
                    <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Tanggal Check-In</label>
                        <input type="date" name="check_in" id="check_in" min="{{ date('Y-m-d') }}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-brand-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Tanggal Check-Out</label>
                        <input type="date" name="check_out" id="check_out" min="{{ date('Y-m-d', strtotime('+1 day')) }}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-brand-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Catatan Khusus (Opsional)</label>
                        <textarea name="notes" rows="2" placeholder="Permintaan khusus / waktu kedatangan..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500"></textarea>
                    </div>

                    <!-- Price Calculation Card -->
                    <div id="priceCalculationCard" class="bg-slate-900/80 border border-slate-700/80 rounded-xl p-4 space-y-2 hidden">
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>Harga / malam</span>
                            <span>Rp {{ number_format($apartment->price_per_night, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>Durasi Menginap</span>
                            <span id="calcNights">0 malam</span>
                        </div>
                        <div class="border-t border-slate-700 pt-2 flex justify-between font-bold text-sm text-white">
                            <span>Total Pembayaran</span>
                            <span id="calcTotalPrice" class="text-brand-400">Rp 0</span>
                        </div>
                    </div>

                    @auth
                        <button type="submit" id="submitBtn" class="w-full py-3.5 bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-brand-500/25 transition-all flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Booking Sekarang
                        </button>
                    @else
                        <a href="{{ route('login') }}" class="w-full py-3.5 bg-slate-700 hover:bg-slate-600 text-white font-bold text-sm rounded-xl text-center block transition-colors">
                            Masuk Untuk Booking
                        </a>
                    @endauth
                </form>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        const calcCard = document.getElementById('priceCalculationCard');
        const calcNights = document.getElementById('calcNights');
        const calcTotalPrice = document.getElementById('calcTotalPrice');
        const pricePerNight = {{ $apartment->price_per_night }};

        function calculate() {
            if (checkInInput.value && checkOutInput.value) {
                const date1 = new Date(checkInInput.value);
                const date2 = new Date(checkOutInput.value);
                
                if (date2 > date1) {
                    const diffTime = Math.abs(date2 - date1);
                    const nights = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    const total = nights * pricePerNight;

                    calcNights.textContent = nights + ' malam';
                    calcTotalPrice.textContent = 'Rp ' + total.toLocaleString('id-ID');
                    calcCard.classList.remove('hidden');
                } else {
                    calcCard.classList.add('hidden');
                }
            }
        }

        checkInInput.addEventListener('change', function() {
            if (checkInInput.value) {
                const nextDay = new Date(checkInInput.value);
                nextDay.setDate(nextDay.getDate() + 1);
                checkOutInput.min = nextDay.toISOString().split('T')[0];
                if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
                    checkOutInput.value = nextDay.toISOString().split('T')[0];
                }
            }
            calculate();
        });

        checkOutInput.addEventListener('change', calculate);
    });
</script>
@endpush
@endsection
