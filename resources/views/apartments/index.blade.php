@extends('layouts.app')

@section('title', 'Daftar Apartemen - ApartStay')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-white tracking-tight">Daftar Apartemen</h1>
        <p class="text-slate-400 text-sm mt-1">Temukan dan sewa unit apartemen terbaik di berbagai lokasi strategis.</p>
    </div>

    <!-- Filter Bar -->
    <div class="bg-slate-800/80 border border-slate-700 p-5 rounded-2xl mb-10 shadow-lg">
        <form action="{{ route('apartments.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Cari Nama / Alamat</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ketik kata kunci..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Kota</label>
                <select name="city" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-brand-500">
                    <option value="">Semua Kota</option>
                    @foreach($cities as $city)
                        <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>{{ $city }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Harga Maks / Malam</label>
                <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Rp maks..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Urutkan</label>
                <select name="sort" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:border-brand-500">
                    <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Terbaru</option>
                    <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Harga Termurah</option>
                    <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Harga Tertinggi</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-brand-500 hover:bg-brand-600 font-semibold text-sm text-white rounded-xl transition-colors">Filter</button>
                <a href="{{ route('apartments.index') }}" class="py-2 px-3 bg-slate-700 hover:bg-slate-600 text-xs text-slate-300 rounded-xl transition-colors">Reset</a>
            </div>
        </form>
    </div>

    <!-- Apartments Grid -->
    @if($apartments->isEmpty())
        <div class="text-center py-16 bg-slate-800/40 border border-slate-800 rounded-2xl">
            <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <h3 class="text-lg font-bold text-white mb-1">Apartemen Tidak Ditemukan</h3>
            <p class="text-sm text-slate-400">Coba atur ulang filter pencarian Anda untuk melihat pilihan lainnya.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-10">
            @foreach($apartments as $apt)
                <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl overflow-hidden hover:border-brand-500/50 hover:shadow-xl transition-all duration-300 group flex flex-col">
                    <div class="relative h-56 overflow-hidden">
                        <img src="{{ $apt->main_image }}" alt="{{ $apt->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <span class="absolute top-3 left-3 bg-brand-500/90 text-white font-bold text-xs px-2.5 py-1 rounded-md backdrop-blur-md">
                            {{ $apt->city }}
                        </span>
                        <div class="absolute bottom-3 right-3 bg-slate-900/90 backdrop-blur-md px-3 py-1 rounded-lg border border-slate-700">
                            <span class="text-[10px] text-slate-400 block">Harga / malam</span>
                            <span class="text-sm font-extrabold text-brand-400">Rp {{ number_format($apt->price_per_night, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="p-5 flex-grow flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-lg text-white group-hover:text-brand-400 transition-colors line-clamp-1 mb-1">{{ $apt->title }}</h3>
                            <p class="text-xs text-slate-400 mb-3 line-clamp-1 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                {{ $apt->address }}
                            </p>

                            <div class="flex items-center gap-3 text-xs text-slate-300 py-2.5 border-y border-slate-700/60 mb-4">
                                <span>{{ $apt->bedrooms }} Bed</span>
                                <span>•</span>
                                <span>{{ $apt->bathrooms }} Bath</span>
                                <span>•</span>
                                <span>{{ $apt->area_sqm }} m²</span>
                                <span>•</span>
                                <span>Maks {{ $apt->capacity }} Tamu</span>
                            </div>
                        </div>

                        <a href="{{ route('apartments.show', $apt->slug) }}" class="w-full py-2.5 bg-slate-700/60 hover:bg-brand-600 text-white font-semibold text-sm rounded-xl text-center transition-colors">
                            Lihat Detail
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $apartments->links() }}
        </div>
    @endif
</div>
@endsection
