@extends('layouts.app')

@section('title', 'Katalog Unit — Santhosa')
@section('meta_description', 'Telusuri koleksi apartemen terkurasi Santhosa berdasarkan kota, kapasitas, dan rentang harga.')

@php
    $activeFilters = collect(request()->only(['search', 'city', 'min_price', 'max_price', 'bedrooms', 'capacity']))
        ->filter(fn ($value) => filled($value));
@endphp

@section('content')
    <section class="border-b border-ink-300 bg-paper-200">
        <div class="site-container py-14 sm:py-16">
            <nav class="annotation flex items-center gap-2 uppercase" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="transition-colors hover:text-blueprint-700">Beranda</a>
                <span aria-hidden="true">/</span>
                <span class="text-ink-700" aria-current="page">Katalog unit</span>
            </nav>

            <div class="mt-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <h1 class="text-4xl font-bold tracking-[-0.02em] text-ink-900 sm:text-5xl">Katalog unit</h1>
                <p class="max-w-md text-sm leading-7 text-ink-600">
                    Menampilkan <span class="font-annotation font-bold text-ink-900">{{ $apartments->total() }} unit</span>
                    yang tersedia untuk disewa saat ini.
                </p>
            </div>
        </div>
    </section>

    <section class="bg-paper-100 py-12 sm:py-16">
        <div class="site-container grid gap-10 lg:grid-cols-[248px_1fr] lg:gap-14">
            {{-- Indeks & filter: <details> supaya bisa dilipat di mobile tanpa JS,
                 dan selalu terbuka di lg karena summary-nya disembunyikan. --}}
            <details open class="h-max border border-ink-200 bg-paper-50 lg:sticky lg:top-24 lg:border-0 lg:bg-transparent">
                <summary class="flex min-h-12 cursor-pointer items-center justify-between border-b border-ink-200 px-4 font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-800 lg:hidden">
                    Filter &amp; urutkan
                    @if($activeFilters->isNotEmpty())
                        <span class="ml-2 bg-dimension-600 px-2 py-0.5 font-annotation text-xs font-bold text-paper-50">{{ $activeFilters->count() }}</span>
                    @endif
                </summary>

                <form action="{{ route('apartments.index') }}" method="GET" class="space-y-7 p-4 lg:p-0" data-submit-loading>
                    <div>
                        <h2 class="annotation annotation--strong uppercase">Cari unit</h2>
                        <label class="field mt-4">
                            <span class="field__label">Nama atau alamat</span>
                            <input type="search" name="search" value="{{ request('search') }}" placeholder="Contoh: Sudirman" autocomplete="off" class="field__control">
                        </label>
                    </div>

                    <div>
                        <h2 class="annotation annotation--strong uppercase">Lokasi &amp; tamu</h2>
                        <div class="mt-4 space-y-2">
                            <label class="field">
                                <span class="field__label">Kota</span>
                                <select name="city" class="field__control">
                                    <option value="">Semua kota</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city }}" @selected(request('city') == $city)>{{ $city }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="field">
                                <span class="field__label">Jumlah tamu</span>
                                <input type="number" name="capacity" value="{{ request('capacity') }}" min="1" inputmode="numeric" placeholder="Semua kapasitas" class="field__control">
                            </label>
                        </div>
                    </div>

                    <div>
                        <h2 class="annotation annotation--strong uppercase">Rentang harga</h2>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <label class="field">
                                <span class="field__label">Min / malam</span>
                                <input type="number" name="min_price" value="{{ request('min_price') }}" min="0" step="50000" inputmode="numeric" placeholder="IDR 0" class="field__control">
                            </label>
                            <label class="field">
                                <span class="field__label">Maks / malam</span>
                                <input type="number" name="max_price" value="{{ request('max_price') }}" min="0" step="50000" inputmode="numeric" placeholder="Tanpa batas" class="field__control">
                            </label>
                        </div>
                    </div>

                    <div>
                        <h2 class="annotation annotation--strong uppercase">Urutan</h2>
                        <label class="field mt-4">
                            <span class="field__label">Tampilkan berdasarkan</span>
                            <select name="sort" class="field__control">
                                <option value="newest" @selected(request('sort', 'newest') === 'newest')>Terbaru</option>
                                <option value="price_low" @selected(request('sort') === 'price_low')>Harga termurah</option>
                                <option value="price_high" @selected(request('sort') === 'price_high')>Harga tertinggi</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-ink-200 pt-6">
                        <button type="submit" class="btn-primary w-full" data-loading-label="Mencari...">
                            <span>Terapkan filter</span>
                        </button>
                        @if($activeFilters->isNotEmpty() || request('sort'))
                            <a href="{{ route('apartments.index') }}" class="btn-secondary w-full">
                                Reset filter
                            </a>
                        @endif
                    </div>
                </form>
            </details>

            <div class="min-w-0">
                @if($apartments->isEmpty())
                    @php $isFiltered = $activeFilters->isNotEmpty(); @endphp
                    <div class="empty-state graph-grid">
                        <svg class="h-10 w-10 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h2m-2 4h2m2-4h2m-2 4h2m-6 10v-5h6v5" />
                        </svg>
                        <h2 class="mt-4 text-2xl font-bold text-ink-900">
                            {{ $isFiltered ? 'Unit tidak ditemukan' : 'Belum ada unit tersedia' }}
                        </h2>
                        <p class="mt-2 max-w-md text-sm leading-6 text-ink-600">
                            {{ $isFiltered
                                ? 'Tidak ada unit yang cocok dengan filter Anda. Coba longgarkan rentang harga atau pilih kota lain.'
                                : 'Saat ini tidak ada unit yang dibuka untuk reservasi. Unit baru akan tampil di halaman ini begitu tersedia.' }}
                        </p>
                        @if($isFiltered)
                            <a href="{{ route('apartments.index') }}" class="btn-primary mt-6">Reset filter</a>
                        @else
                            <a href="{{ route('home') }}" class="btn-primary mt-6">Kembali ke beranda</a>
                        @endif
                    </div>
                @else
                    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b border-ink-300 pb-5">
                        <p class="annotation uppercase">
                            Lembar {{ $apartments->firstItem() }}–{{ $apartments->lastItem() }} dari {{ $apartments->total() }} unit
                        </p>
                        @if($activeFilters->isNotEmpty())
                            <p class="annotation text-dimension-600 uppercase">
                                {{ $activeFilters->count() }} filter aktif
                            </p>
                        @endif
                    </div>

                    <div class="mt-10 grid gap-x-6 gap-y-12 sm:grid-cols-2">
                        @foreach($apartments as $apt)
                            <x-apartment-card
                                :apartment="$apt"
                                :priority="$loop->first"
                                aspect="aspect-[16/11]"
                            />
                        @endforeach
                    </div>

                    <div class="mt-14">
                        {{ $apartments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
