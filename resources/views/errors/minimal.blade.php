{{--
    Satu file ini menggantikan layout error bawaan Laravel untuk SEMUA kode HTTP
    (401/402/403/404/419/429/500/503) — view errors::xxx milik framework semuanya
    @extends('errors::minimal'), dan resources/views/errors didahulukan di
    namespace 'errors'. Tanpa file ini pengunjung melihat halaman putih
    berbahasa Inggris tanpa navigasi.
--}}
@extends('layouts.app')

@php
    $code = trim($__env->yieldContent('code')) ?: '500';

    [$heading, $body] = match ($code) {
        '401', '403' => ['Akses ditolak', 'Anda tidak memiliki izin untuk membuka halaman ini. Coba masuk memakai akun yang berhak.'],
        '404' => ['Halaman tidak ditemukan', 'Tautannya mungkin salah, atau unit yang Anda cari sudah tidak tersedia lagi.'],
        '419' => ['Sesi Anda kedaluwarsa', 'Halaman terbuka terlalu lama. Muat ulang halaman, lalu kirim ulang data Anda.'],
        '429' => ['Terlalu banyak permintaan', 'Permintaan Anda terlalu cepat berurutan. Tunggu sekitar satu menit, lalu coba lagi.'],
        '503' => ['Sedang dalam pemeliharaan', 'Kami sedang melakukan pemeliharaan singkat. Silakan coba beberapa saat lagi.'],
        default => ['Terjadi kesalahan', 'Ada gangguan di sisi kami — bukan pada data Anda. Coba muat ulang halaman; bila masih gagal, coba lagi nanti.'],
    };
@endphp

{{-- Blok + @overwrite (bukan bentuk satu baris): section 'title' sudah diisi
     view errors::xxx milik framework, dan @overwrite yang menimpanya. --}}
@section('title')
    {{ $code }} — {{ $heading }} — Santhosa
@overwrite

@section('content')
    <section class="bg-ink-950 py-20 sm:py-28">
        <div class="site-container">
            <div class="mx-auto max-w-xl border border-white/10 bg-ink-900 p-7 text-center sm:p-10">
                <p class="font-mono text-[10px] font-bold uppercase tracking-[0.24em] text-gold-400">Error {{ $code }}</p>
                <h1 class="mt-4 font-display text-3xl font-semibold tracking-[-0.025em] text-white sm:text-4xl">{{ $heading }}</h1>
                <p class="mx-auto mt-4 max-w-md text-sm leading-6 text-ink-300">{{ $body }}</p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('home') }}" class="gold-button w-full sm:w-auto">Kembali ke beranda</a>
                    <a href="{{ route('apartments.index') }}"
                        class="inline-flex min-h-11 w-full items-center justify-center border border-white/15 px-5 text-[11px] font-bold uppercase tracking-[0.1em] text-ink-200 transition-colors hover:border-gold-400/50 hover:text-white sm:w-auto">
                        Lihat katalog unit
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
