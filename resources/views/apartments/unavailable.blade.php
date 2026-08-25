@extends('layouts.app')

@section('title', $apartment->title . ' — Sedang tidak tersedia — Santhosa')
@section('meta_description', 'Unit ini sedang tidak dibuka untuk reservasi. Telusuri unit lain yang tersedia di katalog Santhosa.')

{{-- Bukan penawaran aktif, jadi jangan bersaing di hasil pencarian. Tetap 200:
     halaman ini benar-benar ada dan menjelaskan keadaan, bukan halaman hilang. --}}
@section('robots', 'noindex, follow')

@section('content')
    <section class="border-b border-white/10 bg-ink-900">
        <div class="site-container py-12 sm:py-14">
            <nav class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-ink-400" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="transition-colors hover:text-gold-300">Beranda</a>
                <span aria-hidden="true">›</span>
                <a href="{{ route('apartments.index') }}" class="transition-colors hover:text-gold-300">Katalog Unit</a>
                <span aria-hidden="true">›</span>
                <span class="truncate text-ink-200" aria-current="page">{{ $apartment->title }}</span>
            </nav>
        </div>
    </section>

    <section class="bg-ink-950 py-12 sm:py-16">
        <div class="site-container">
            <div class="empty-state mx-auto max-w-2xl">
                <svg class="h-10 w-10 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                        d="M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h2m2 0h2M9 11h2m2 0h2m-6 10v-5h6v5" />
                    <path stroke-linecap="round" stroke-width="1.6" d="m4 4 16 16" />
                </svg>

                <h1 class="mt-5 font-display text-3xl font-semibold tracking-[-0.025em] text-white sm:text-4xl">
                    Unit tidak tersedia
                </h1>

                <p class="mt-4 text-sm font-bold uppercase tracking-[0.14em] text-ink-400">
                    {{ $apartment->title }} · {{ $apartment->city }}
                </p>

                {{-- Tanpa membocorkan detail internal: cukup keadaannya, bukan
                     status enum, alasan operasional, atau perkiraan tanggal yang
                     tidak dicatat sistem. --}}
                <p class="mx-auto mt-5 max-w-md text-sm leading-6 text-ink-300">
                    Unit ini sedang dalam proses pemeliharaan dan belum dapat dipesan. Reservasi baru akan dibuka
                    kembali di halaman ini begitu unitnya siap.
                </p>

                <div class="mt-8 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row">
                    <a href="{{ route('apartments.index') }}" class="gold-button w-full sm:w-auto">Lihat apartemen lain</a>
                    <a href="{{ route('apartments.index', ['city' => $apartment->city]) }}"
                        class="inline-flex min-h-11 w-full items-center justify-center border border-white/15 px-5 text-xs font-bold uppercase tracking-[0.1em] text-ink-200 transition-colors hover:border-gold-400/50 hover:text-white sm:w-auto">
                        Unit lain di {{ $apartment->city }}
                    </a>
                </div>

                <p class="mt-7 border-t border-white/10 pt-6 text-xs leading-5 text-ink-400">
                    Butuh unit ini secara khusus? Hubungi
                    <a href="mailto:{{ config('support.email') }}" class="font-semibold text-gold-400 transition-colors hover:text-gold-200">{{ config('support.email') }}</a>.
                </p>
            </div>
        </div>
    </section>
@endsection
