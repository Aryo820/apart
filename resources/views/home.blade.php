@extends('layouts.app')

@section('title', 'Santhosa — Hunian Berkelas, Kenyamanan Tanpa Batas')
@section('meta_description', 'Jelajahi koleksi apartemen premium Santhosa di lokasi strategis dan pesan hunian pilihan Anda secara aman.')

@php
    $heroApartment = $featuredApartments->first();
    $heroImage = $heroApartment?->main_image_url;
@endphp

@section('content')
    <section class="relative isolate overflow-hidden border-b border-white/10 bg-ink-950">
        @if($heroImage)
            <img
                src="{{ $heroImage }}"
                alt=""
                width="1800"
                height="1100"
                class="absolute inset-0 h-full w-full object-cover object-center"
                fetchpriority="high"
                decoding="async"
            >
        @endif

        <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(4,10,20,.34)_0%,rgba(4,10,20,.42)_40%,rgba(4,10,20,.88)_100%)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_0%,rgba(4,10,20,.16)_48%,rgba(4,10,20,.72)_100%)]"></div>

        {{-- Tinggi hero mengikuti viewport (svh, jadi ikut menyusut saat toolbar
             browser mobile muncul) alih-alih floor px yang besar. Padding mobile
             ditahan kecil karena isi hero (judul + paragraf + form 3 baris ≈
             500px) harus utuh di dalam 100svh-72px sebuah iPhone SE — dan karena
             isinya di-center, padding itu tidak terlihat di layar yang lega.
             Di sm/lg padding kembali lapang dan proporsinya tidak berubah. --}}
        <div class="site-container relative z-10 flex min-h-[calc(100svh-72px)] flex-col items-center justify-center pb-4 pt-8 sm:min-h-[780px] sm:pb-16 sm:pt-36 lg:min-h-[calc(100svh-72px)]">
            <div class="mx-auto max-w-4xl text-center">
                <p class="section-eyebrow text-white/70">Pilihan utama</p>
                <h1 class="mt-4 font-display text-[2.25rem] font-semibold leading-[1.05] tracking-[-0.035em] text-white sm:mt-5 sm:text-6xl lg:text-[5rem]">
                    Hunian Berkelas,<br>
                    Kenyamanan Tanpa Batas
                </h1>
                <p class="mx-auto mt-4 max-w-xl text-sm leading-6 text-white/75 sm:mt-6 sm:leading-7 sm:text-base">
                    Hunian premium terkurasi untuk perjalanan bisnis, rehat singkat, dan momen yang layak dikenang.
                </p>
            </div>

            <form
                action="{{ route('apartments.index') }}"
                method="GET"
                class="mt-6 w-full max-w-5xl border border-white/10 bg-[#15213a]/95 p-2.5 shadow-[0_24px_70px_rgba(0,0,0,.35)] backdrop-blur-md sm:mt-20"
                data-submit-loading
            >
                {{-- Mobile: 2 kolom, jadi kota + tamu berbagi satu baris dan
                     form hanya 3 baris, bukan 4. Itu ~64px yang menentukan
                     apakah tombol Cari terlihat tanpa scroll di layar 667px. --}}
                <div class="grid grid-cols-2 gap-2 md:grid-cols-[1.25fr_1fr_1fr_auto]">
                    <label class="search-field col-span-2 md:col-span-1">
                        <span class="search-field__label">Lokasi atau unit</span>
                        <span class="relative block">
                            <svg class="pointer-events-none absolute left-0 top-1/2 h-4 w-4 -translate-y-1/2 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1 1 18 0Z" />
                                <circle cx="12" cy="10" r="3" stroke-width="1.8" />
                            </svg>
                            <input
                                type="search"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Nama apartemen atau area"
                                autocomplete="off"
                                class="search-field__control pl-6"
                            >
                        </span>
                    </label>

                    <label class="search-field">
                        <span class="search-field__label">Kota</span>
                        <select name="city" class="search-field__control">
                            <option value="">Semua kota</option>
                            @foreach($cities as $city)
                                <option value="{{ $city }}">{{ $city }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="search-field">
                        <span class="search-field__label">Jumlah tamu</span>
                        <select name="capacity" class="search-field__control">
                            <option value="">Pilih kapasitas</option>
                            @for($guest = 1; $guest <= $maxCapacity; $guest++)
                                <option value="{{ $guest }}">{{ $guest }} tamu</option>
                            @endfor
                        </select>
                    </label>

                    <button type="submit" class="gold-button col-span-2 min-h-14 px-8 md:col-span-1 md:min-w-40" data-loading-label="Mencari...">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" stroke-width="2" />
                            <path stroke-linecap="round" stroke-width="2" d="m20 20-3.5-3.5" />
                        </svg>
                        <span>Cari</span>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section id="units" class="bg-ink-950 py-20 sm:py-24 lg:py-28">
        <div class="site-container">
            <div class="mb-10 flex flex-col gap-5 sm:mb-12 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="section-eyebrow">Koleksi eksklusif</p>
                    <h2 class="mt-3 font-display text-4xl font-semibold tracking-[-0.025em] text-white sm:text-5xl">Unit Pilihan</h2>
                </div>

                <a href="{{ route('apartments.index') }}" class="inline-link self-start sm:self-auto">
                    Lihat semua unit
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" />
                    </svg>
                </a>
            </div>

            @if($featuredApartments->isEmpty())
                <div class="empty-state">
                    <svg class="h-10 w-10 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h2m-2 4h2m2-4h2m-2 4h2m-6 10v-5h6v5" />
                    </svg>
                    <h3 class="mt-4 font-display text-2xl text-white">Unit pilihan sedang disiapkan</h3>
                    <p class="mt-2 max-w-md text-sm leading-6 text-ink-300">Jelajahi katalog lengkap untuk melihat seluruh unit yang tersedia saat ini.</p>
                    <a href="{{ route('apartments.index') }}" class="gold-button mt-6">Buka katalog</a>
                </div>
            @else
                <div class="grid gap-x-6 gap-y-12 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($featuredApartments as $apartment)
                        <x-apartment-card :apartment="$apartment" :priority="$loop->first" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="facilities" class="border-y border-white/10 bg-ink-900 py-20 sm:py-24 lg:py-28">
        <div class="site-container">
            <div class="mx-auto max-w-2xl text-center">
                <p class="section-eyebrow">Layanan premium</p>
                <h2 class="mt-3 font-display text-4xl font-semibold tracking-[-0.025em] text-white sm:text-5xl">Fasilitas Unggulan</h2>
                <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-ink-300">
                    Fasilitas yang menyertai setiap unit, mulai dari kenyamanan harian sampai kebutuhan kerja Anda.
                </p>
            </div>

            @if($facilities->isEmpty())
                <div class="empty-state mt-12">
                    <x-facility-icon name="sparkles" class="h-10 w-10 text-gold-400" />
                    <h3 class="mt-4 font-display text-2xl text-white">Fasilitas belum tersedia</h3>
                    <p class="mt-2 text-sm text-ink-300">Jelajahi katalog unit untuk melihat fasilitas yang tersedia di masing-masing unit.</p>
                </div>
            @else
                {{-- Flex-wrap, bukan grid: lebar kartu dihitung dari lebar baris
                     (1 kolom → 2 → 3), sementara baris terakhir yang tidak penuh
                     ikut ter-center alih-alih menggantung di kiri. Border dipasang
                     per kartu, bukan dibagi antar sel, jadi tidak ada sel kosong
                     atau garis terputus — berlaku untuk 1 sampai N fasilitas,
                     apa pun yang diisi admin. --}}
                <div class="mt-12 flex flex-wrap justify-center gap-4">
                    @foreach($facilities as $facility)
                        <article class="flex w-full flex-col items-center border border-white/10 bg-ink-950/40 px-6 py-9 text-center sm:w-[calc(50%-0.5rem)] md:px-8 lg:w-[calc(33.333%-0.667rem)]">
                            <div class="flex h-12 w-12 items-center justify-center text-gold-400">
                                <x-facility-icon :name="$facility->icon" class="h-7 w-7" />
                            </div>
                            <h3 class="mt-4 text-base font-semibold text-white">{{ $facility->name }}</h3>
                            <p class="mt-2 max-w-sm text-sm leading-6 text-ink-300">
                                {{ $facility->description ?: 'Fasilitas tersedia untuk mendukung kenyamanan selama Anda menginap.' }}
                            </p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
