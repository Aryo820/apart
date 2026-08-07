@extends('layouts.app')

@section('title', 'ApartStay - Temukan Apartemen Mewah Impian Anda')

@section('content')
<!-- Hero Section -->
<section class="relative min-h-[580px] flex items-center justify-center pt-8 pb-16 overflow-hidden hero-gradient">
    <!-- Background Glow & Shapes -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-brand-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
        <div class="text-center max-w-3xl mx-auto mb-10">
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest bg-brand-500/10 text-brand-400 border border-brand-500/20 mb-4">
                <span class="w-2 h-2 rounded-full bg-brand-400 animate-ping"></span>
                Sewa Apartemen Premium Harian & Bulanan
            </span>
            <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight leading-tight mb-6">
                Pengalaman Menginap <br class="hidden sm:inline">
                <span class="gradient-text">Berkelas &amp; Tanpa Ribet</span>
            </h1>
            <p class="text-lg text-slate-300 leading-relaxed">
                Nikmati kenyamanan hunian apartemen terbaik lengkap dengan kolam renang infinity, keamanan 24 jam, dan akses cepat ke pusat kota.
            </p>
        </div>

        <!-- Search Bar Card -->
        <div class="max-w-4xl mx-auto bg-slate-800/90 backdrop-blur-xl p-4 sm:p-6 rounded-2xl border border-slate-700 shadow-2xl shadow-brand-950/50">
            <form action="{{ route('apartments.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Cari Kata Kunci / Lokasi</label>
                    <div class="relative">
                        <input type="text" name="search" placeholder="Nama apartemen atau area..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Pilih Kota</label>
                    <select name="city" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-brand-500 transition-colors">
                        <option value="">Semua Kota</option>
                        @foreach($cities as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Jumlah Tamu</label>
                    <select name="capacity" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-brand-500 transition-colors">
                        <option value="">Berapa Tamu?</option>
                        @for($guest = 1; $guest <= $maxCapacity; $guest++)
                            <option value="{{ $guest }}">{{ $guest }} Tamu</option>
                        @endfor
                    </select>
                </div>

                <div>
                    <button type="submit" class="w-full py-2.5 px-6 font-bold text-white bg-gradient-to-r from-brand-500 to-brand-600 rounded-xl hover:from-brand-600 hover:to-brand-700 transition-all shadow-lg shadow-brand-500/25 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Cari Apartemen
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Featured Apartments Section -->
<section class="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-10 gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-white tracking-tight">Apartemen Pilihan Terfavorit</h2>
            <p class="text-slate-400 text-sm mt-1">Hunian eksklusif dengan ulasan tertinggi dan fasilitas lengkap.</p>
        </div>
        <a href="{{ route('apartments.index') }}" class="text-brand-400 hover:text-brand-300 font-semibold text-sm flex items-center gap-1 group">
            Lihat Semua Apartemen
            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @foreach($featuredApartments as $apt)
            <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl overflow-hidden hover:border-brand-500/50 hover:shadow-xl hover:shadow-brand-950/40 transition-all duration-300 group flex flex-col">
                <!-- Main Image Container -->
                <div class="relative h-56 overflow-hidden">
                    <img src="{{ $apt->main_image }}" alt="{{ $apt->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent"></div>
                    
                    <span class="absolute top-3 left-3 bg-brand-500/90 text-white font-bold text-xs px-2.5 py-1 rounded-md backdrop-blur-md">
                        {{ $apt->city }}
                    </span>

                    <div class="absolute bottom-3 right-3 bg-slate-900/90 backdrop-blur-md px-3 py-1 rounded-lg border border-slate-700">
                        <span class="text-xs text-slate-400">Mulai dari</span>
                        <div class="text-sm font-extrabold text-brand-400">Rp {{ number_format($apt->price_per_night, 0, ',', '.') }}<span class="text-[10px] text-slate-400 font-normal"> / malam</span></div>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-5 flex-grow flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-lg text-white group-hover:text-brand-400 transition-colors line-clamp-1 mb-2">
                            {{ $apt->title }}
                        </h3>
                        <p class="text-xs text-slate-400 flex items-center gap-1 mb-4 line-clamp-1">
                            <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $apt->address }}
                        </p>

                        <!-- Specs Pills -->
                        <div class="flex items-center gap-4 text-xs text-slate-300 py-3 border-y border-slate-700/60 mb-4">
                            <span class="flex items-center gap-1.5"><svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> {{ $apt->bedrooms }} Kamar</span>
                            <span class="flex items-center gap-1.5"><svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg> {{ $apt->bathrooms }} WC</span>
                            <span class="flex items-center gap-1.5"><svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-2V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg> {{ $apt->area_sqm }} m²</span>
                        </div>
                    </div>

                    <a href="{{ route('apartments.show', $apt->slug) }}" class="w-full py-2.5 bg-slate-700/60 hover:bg-brand-600 text-white font-semibold text-sm rounded-xl text-center transition-colors">
                        Lihat Detail &amp; Sewa
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</section>

<!-- Facilities Showcase -->
<section class="py-16 bg-slate-950/60 border-y border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-3xl font-extrabold text-white">Fasilitas Standar Bintang Lima</h2>
            <p class="text-slate-400 text-sm mt-2">Semua unit apartemen kami dirancang untuk memberikan kenyamanan maksimal.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
            @foreach($facilities as $facility)
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-center hover:border-brand-500/40 transition-colors group">
                    <div class="w-12 h-12 rounded-xl bg-brand-500/10 border border-brand-500/20 text-brand-400 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h4 class="font-bold text-white text-sm mb-1">{{ $facility->name }}</h4>
                    <p class="text-xs text-slate-400 line-clamp-2">{{ $facility->description }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Why Choose Us CTA -->
<section class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-gradient-to-r from-brand-900/60 via-slate-800 to-slate-900 border border-brand-500/30 rounded-3xl p-8 sm:p-12 relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-8">
        <div class="space-y-4 max-w-2xl z-10">
            <span class="text-brand-400 text-xs font-bold uppercase tracking-wider">Garansi Kepuasan Menginap</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Siap Untuk Pengalaman Menginap Terbaik?</h2>
            <p class="text-slate-300 text-sm leading-relaxed">Proses pemesanan serba instan dalam 2 menit. Dapatkan konfirmasi otomatis dan dukungan layanan pelanggan 24 jam.</p>
        </div>
        <div class="z-10 flex-shrink-0">
            <a href="{{ route('apartments.index') }}" class="px-8 py-4 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-2xl shadow-xl shadow-brand-500/30 transition-all inline-block">
                Jelajahi Apartemen
            </a>
        </div>
    </div>
</section>
@endsection
