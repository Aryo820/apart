@extends('layouts.app')

@section('title', 'Santhosa — Apartemen Terkurasi, Langsung dari Pemilik')
@section('meta_description', 'Unit apartemen terkurasi dengan spesifikasi lengkap dan harga transparan. Pesan langsung dari pemilik, konfirmasi otomatis setelah pembayaran.')

@php
    $heroApartment = $featuredApartments->first();
    $heroImage = $heroApartment?->display_image_url;
@endphp

@section('content')
    {{-- Cover brosur: kertas milimeter, masthead besar, plat unit unggulan
         yang diukur garis dimensi, dan formulir pencarian teknis. --}}
    <section class="graph-grid relative overflow-hidden border-b border-ink-300 bg-paper-100">
        <div class="site-container flex flex-col gap-10 pb-14 pt-10 sm:pt-14 lg:min-h-[calc(100svh-72px)] lg:grid lg:grid-cols-[1.1fr_1fr] lg:items-center lg:gap-14 lg:pb-20">
            <div>
                <h1 class="max-w-xl text-[2.5rem] font-xwide font-extrabold uppercase leading-[0.98] tracking-[-0.02em] text-ink-900 sm:text-6xl lg:text-[4.25rem]">
                    Hunian terukur,<br>
                    langsung dari<br>
                    pemilik.
                </h1>

                {{-- Alur pesan sebagai "ukuran" di bawah masthead: tiga langkah
                     yang benar-benar terjadi di sistem, bukan slogan. --}}
                <div class="dim mt-7 max-w-md" aria-hidden="true">
                    <span class="dim__label">Pesan · Bayar · Terkonfirmasi</span>
                </div>

                <p class="mt-7 max-w-lg text-base leading-7 text-ink-600">
                    Apartemen terkurasi dengan spesifikasi yang tercatat lengkap — luas, fasilitas, dan tarif per
                    malam. Dipesan tanpa perantara, dikonfirmasi otomatis begitu pembayaran diterima.
                </p>

                <form
                    action="{{ route('apartments.index') }}"
                    method="GET"
                    class="mt-9 border border-ink-300 bg-paper-50 p-2.5 shadow-[0_16px_40px_-24px_rgba(23,26,21,.35)]"
                    data-submit-loading
                >
                    {{-- Mobile: 2 kolom, jadi kota + tamu berbagi satu baris dan
                         form hanya 3 baris — menentukan apakah tombol Cari
                         terlihat tanpa scroll di layar pendek. --}}
                    <div class="grid grid-cols-2 gap-2 md:grid-cols-[1.25fr_1fr_1fr_auto]">
                        <label class="field col-span-2 md:col-span-1">
                            <span class="field__label">Lokasi atau unit</span>
                            <span class="relative block">
                                <svg class="pointer-events-none absolute left-0 top-1/2 h-4 w-4 -translate-y-1/2 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1 1 18 0Z" />
                                    <circle cx="12" cy="10" r="3" stroke-width="1.8" />
                                </svg>
                                <input
                                    type="search"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Nama apartemen atau area"
                                    autocomplete="off"
                                    class="field__control pl-6"
                                >
                            </span>
                        </label>

                        <label class="field">
                            <span class="field__label">Kota</span>
                            <select name="city" class="field__control">
                                <option value="">Semua kota</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city }}">{{ $city }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="field__label">Jumlah tamu</span>
                            <select name="capacity" class="field__control">
                                <option value="">Pilih kapasitas</option>
                                @for($guest = 1; $guest <= $maxCapacity; $guest++)
                                    <option value="{{ $guest }}">{{ $guest }} tamu</option>
                                @endfor
                            </select>
                        </label>

                        <button type="submit" class="btn-primary col-span-2 min-h-14 px-8 md:col-span-1 md:min-w-40" data-loading-label="Mencari...">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" stroke-width="2" />
                                <path stroke-linecap="round" stroke-width="2" d="m20 20-3.5-3.5" />
                            </svg>
                            <span>Cari unit</span>
                        </button>
                    </div>
                </form>
            </div>

            @if($heroApartment && $heroImage)
                {{-- Plat unit unggulan: foto di atas paspartu, diukur dua garis
                     dimensi merah yang menggambar sendiri saat halaman dibuka.
                     Label memakai data unit yang sebenarnya. --}}
                <div class="relative mx-auto w-full max-w-xl lg:max-w-none">
                    <div class="plate">
                        <a href="{{ route('apartments.show', $heroApartment->slug) }}" class="block aspect-[4/3] overflow-hidden bg-paper-300">
                            <img
                                src="{{ $heroImage }}"
                                alt="{{ $heroApartment->title }}"
                                width="1200"
                                height="900"
                                class="h-full w-full object-cover"
                                fetchpriority="high"
                                decoding="async"
                            >
                        </a>
                    </div>

                    <svg class="dim-draw pointer-events-none absolute inset-0 h-full w-full overflow-visible text-dimension-600" viewBox="0 0 100 100" preserveAspectRatio="none" fill="none" stroke="currentColor" stroke-width="1" aria-hidden="true">
                        {{-- Garis dimensi horizontal (lebar plat) --}}
                        <path d="M0 -6 V -2" vector-effect="non-scaling-stroke" pathLength="1" />
                        <path d="M100 -6 V -2" vector-effect="non-scaling-stroke" pathLength="1" />
                        <path d="M0 -4 H 100" vector-effect="non-scaling-stroke" pathLength="1" />
                        {{-- Garis dimensi vertikal (tinggi plat), sembunyi di layar kecil --}}
                        <path class="hidden sm:block" d="M104 0 H 108" vector-effect="non-scaling-stroke" pathLength="1" />
                        <path class="hidden sm:block" d="M104 100 H 108" vector-effect="non-scaling-stroke" pathLength="1" />
                        <path class="hidden sm:block" d="M106 0 V 100" vector-effect="non-scaling-stroke" pathLength="1" />
                    </svg>

                    <span class="annotation absolute -top-12 left-1/2 -translate-x-1/2 whitespace-nowrap text-dimension-600">
                        {{ $heroApartment->area_sqm }} m² · {{ $heroApartment->capacity }} tamu
                    </span>
                    <span class="annotation absolute right-0 top-1/2 hidden -translate-y-1/2 rotate-90 whitespace-nowrap text-dimension-600 sm:block" style="transform-origin: right center;">
                        {{ $heroApartment->city }}
                    </span>

                    <div class="mt-5 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                        <p class="annotation uppercase">No. 01 — Koleksi pilihan</p>
                        <p class="min-w-0 truncate font-annotation text-sm font-bold text-ink-900">
                            <a href="{{ route('apartments.show', $heroApartment->slug) }}" class="transition-colors hover:text-blueprint-700">
                                {{ $heroApartment->title }}
                            </a>
                            <span class="font-normal text-ink-500">· IDR {{ number_format($heroApartment->price_per_night, 0, ',', '.') }} / malam</span>
                        </p>
                    </div>
                </div>
            @else
                <div class="empty-state graph-grid mx-auto w-full max-w-xl">
                    <svg class="h-10 w-10 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h2m-2 4h2m2-4h2m-2 4h2m-6 10v-5h6v5" />
                    </svg>
                    <h2 class="mt-4 text-2xl font-bold text-ink-900">Koleksi pertama sedang disiapkan</h2>
                    <p class="mt-2 max-w-md text-sm leading-6 text-ink-600">Jelajahi katalog lengkap untuk melihat seluruh unit yang tersedia saat ini.</p>
                    <a href="{{ route('apartments.index') }}" class="btn-primary mt-6">Buka katalog</a>
                </div>
            @endif
        </div>
    </section>

    <section id="units" class="bg-paper-100 py-20 sm:py-24 lg:py-28">
        <div class="site-container">
            <div class="mb-10 flex flex-col gap-4 border-b border-ink-200 pb-6 sm:mb-12 sm:flex-row sm:items-end sm:justify-between">
                <h2 class="text-4xl font-bold tracking-[-0.02em] text-ink-900 sm:text-5xl">Unit pilihan</h2>
                <a href="{{ route('apartments.index') }}" class="inline-link self-start sm:self-auto">
                    Lihat semua unit
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" />
                    </svg>
                </a>
            </div>

            @if($featuredApartments->isEmpty())
                <div class="empty-state graph-grid">
                    <svg class="h-10 w-10 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h2m-2 4h2m2-4h2m-2 4h2m-6 10v-5h6v5" />
                    </svg>
                    <h3 class="mt-4 text-2xl font-bold text-ink-900">Unit pilihan sedang disiapkan</h3>
                    <p class="mt-2 max-w-md text-sm leading-6 text-ink-600">Jelajahi katalog lengkap untuk melihat seluruh unit yang tersedia saat ini.</p>
                    <a href="{{ route('apartments.index') }}" class="btn-primary mt-6">Buka katalog</a>
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

    {{-- Jadwal fasilitas: daftar bergaris seperti schedule di lembar gambar,
         bukan kartu ikon seragam. --}}
    <section id="facilities" class="border-y border-ink-300 bg-paper-200 py-20 sm:py-24 lg:py-28">
        <div class="site-container">
            <div class="flex flex-col gap-4 border-b border-ink-300 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <h2 class="text-4xl font-bold tracking-[-0.02em] text-ink-900 sm:text-5xl">Fasilitas yang menyertai</h2>
                <p class="annotation max-w-sm uppercase sm:pb-1 sm:text-right">Tercatat per unit pada halaman masing-masing</p>
            </div>

            @if($facilities->isEmpty())
                <div class="empty-state mt-12">
                    <x-facility-icon name="sparkles" class="h-10 w-10 text-blueprint-600" />
                    <h3 class="mt-4 text-2xl font-bold text-ink-900">Fasilitas belum tersedia</h3>
                    <p class="mt-2 text-sm text-ink-600">Jelajahi katalog unit untuk melihat fasilitas yang tersedia di masing-masing unit.</p>
                </div>
            @else
                <ul class="mt-4">
                    @foreach($facilities as $facility)
                        <li class="grid gap-x-8 gap-y-2 border-b border-ink-200 py-6 sm:grid-cols-[auto_16rem_1fr] sm:items-baseline">
                            <span class="text-blueprint-600 sm:self-center">
                                <x-facility-icon :name="$facility->icon" class="h-6 w-6" />
                            </span>
                            <h3 class="text-lg font-bold tracking-[-0.01em] text-ink-900">{{ $facility->name }}</h3>
                            <p class="text-sm leading-6 text-ink-600">
                                {{ $facility->description ?: 'Fasilitas tersedia untuk mendukung kenyamanan selama Anda menginap.' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- Catatan umum: konvensi lembar gambar untuk urutan langkah yang
         benar-benar terjadi di sistem — bernomor karena urutannya informasi. --}}
    <section class="bg-paper-100 py-20 sm:py-24">
        <div class="site-container grid gap-10 lg:grid-cols-[20rem_1fr] lg:gap-16">
            <div>
                <h2 class="text-3xl font-bold tracking-[-0.02em] text-ink-900 sm:text-4xl">Catatan umum</h2>
                <p class="mt-4 text-sm leading-7 text-ink-600">
                    Empat langkah dari memilih tanggal sampai reservasi Anda terkunci — semuanya berjalan di
                    sistem, tanpa menunggu balasan manusia.
                </p>
            </div>

            <ol class="border-t border-ink-300">
                @foreach([
                    ['Pilih tanggal di halaman unit', 'Ketersediaan diperiksa langsung sebelum Anda memesan — tanggal yang sudah terisi digambar arsir.'],
                    ['Reservasi terbit dengan kode sendiri', 'Tanggal langsung dikunci untuk Anda, dan kode reservasi menjadi acuan tunggal seluruh proses.'],
                    ['Bayar melalui Midtrans', 'Detail kartu Anda dimasukkan di halaman Midtrans, bukan di situs ini. Nominalnya persis total di layar.'],
                    ['Terkonfirmasi otomatis', 'Begitu pembayaran dilaporkan, status reservasi berubah sendiri — tidak ada proses "menunggu verifikasi".'],
                ] as $note)
                    <li class="grid gap-x-8 gap-y-1 border-b border-ink-200 py-6 sm:grid-cols-[3.5rem_1fr]">
                        <span class="font-annotation text-sm font-bold text-dimension-600">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <h3 class="text-lg font-bold tracking-[-0.01em] text-ink-900">{{ $note[0] }}</h3>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-ink-600">{{ $note[1] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endsection
