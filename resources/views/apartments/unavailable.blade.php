@extends('layouts.app')

@section('title', $apartment->title . ' — Sedang tidak tersedia — Santhosa')
@section('meta_description', 'Unit ini sedang tidak dibuka untuk reservasi. Telusuri unit lain yang tersedia di katalog Santhosa.')

{{-- Bukan penawaran aktif, jadi jangan bersaing di hasil pencarian. Tetap 200:
     halaman ini benar-benar ada dan menjelaskan keadaan, bukan halaman hilang. --}}
@section('robots', 'noindex, follow')

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
        </div>
    </section>

    <section class="bg-paper-100 py-12 sm:py-16">
        <div class="site-container">
            <div class="empty-state graph-grid mx-auto max-w-2xl">
                <span class="stamp border-ink-500 text-ink-600">Ditutup sementara</span>

                <h1 class="mt-5 text-3xl font-bold tracking-[-0.02em] text-ink-900 sm:text-4xl">
                    Unit tidak tersedia
                </h1>

                <p class="annotation mt-4 uppercase">
                    {{ $apartment->title }} · {{ $apartment->city }}
                </p>

                {{-- Tanpa membocorkan detail internal: cukup keadaannya, bukan
                     status enum, alasan operasional, atau perkiraan tanggal yang
                     tidak dicatat sistem. --}}
                <p class="mx-auto mt-5 max-w-md text-sm leading-6 text-ink-600">
                    Unit ini sedang dalam proses pemeliharaan dan belum dapat dipesan. Reservasi baru akan dibuka
                    kembali di halaman ini begitu unitnya siap.
                </p>

                <div class="mt-8 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row">
                    <a href="{{ route('apartments.index') }}" class="btn-primary w-full sm:w-auto">Lihat apartemen lain</a>
                    <a href="{{ route('apartments.index', ['city' => $apartment->city]) }}"
                        class="btn-secondary w-full sm:w-auto">
                        Unit lain di {{ $apartment->city }}
                    </a>
                </div>

                <p class="mt-7 border-t border-ink-200 pt-6 text-xs leading-5 text-ink-500">
                    Butuh unit ini secara khusus? Hubungi
                    <a href="mailto:{{ config('support.email') }}" class="font-semibold text-blueprint-700 transition-colors hover:text-blueprint-500">{{ config('support.email') }}</a>.
                </p>
            </div>
        </div>
    </section>
@endsection
