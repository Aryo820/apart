@extends('layouts.app')

@php use App\Enums\BookingStatus; @endphp

@section('title', 'Riwayat Booking — Santhosa')

@section('content')
    <section class="border-b border-white/10 bg-ink-900">
        <div class="site-container py-12 sm:py-14">
            <nav class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-ink-400" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="transition-colors hover:text-gold-300">Beranda</a>
                <span aria-hidden="true">›</span>
                <span class="text-ink-200" aria-current="page">Booking Saya</span>
            </nav>

            <div class="mt-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <h1 class="font-display text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">Booking Saya</h1>
                @if($bookings->total() > 0)
                    <p class="text-sm leading-7 text-ink-300">
                        <span class="font-semibold text-ivory-100">{{ $bookings->total() }} reservasi</span> tercatat pada akun Anda.
                    </p>
                @endif
            </div>
        </div>
    </section>

    <section class="bg-ink-950 py-12 sm:py-16">
        <div class="site-container">
            @if($bookings->isEmpty())
                <div class="empty-state">
                    <svg class="h-10 w-10 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" />
                    </svg>
                    <h2 class="mt-4 font-display text-2xl text-white">Belum ada reservasi</h2>
                    <p class="mt-2 max-w-md text-sm leading-6 text-ink-300">
                        Reservasi yang Anda buat akan tampil di sini beserta status pembayarannya.
                    </p>
                    <a href="{{ route('apartments.index') }}" class="gold-button mt-6">Jelajahi katalog unit</a>
                </div>
            @else
                <ul class="space-y-4">
                    @foreach($bookings as $booking)
                        <li class="border border-white/10 bg-ink-900 transition-colors hover:border-gold-400/30">
                            <article class="flex flex-col gap-6 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex min-w-0 items-start gap-5">
                                    <img
                                        src="{{ $booking->apartment->main_image_url }}"
                                        alt="{{ $booking->apartment->title }}"
                                        width="112"
                                        height="112"
                                        class="h-20 w-20 shrink-0 bg-ink-800 object-cover sm:h-28 sm:w-28"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                    <div class="min-w-0">
                                        <p class="font-mono text-[11px] font-bold tracking-[0.08em] text-gold-400">{{ $booking->booking_code }}</p>
                                        <h2 class="mt-1.5 font-display text-xl font-semibold leading-snug text-white">
                                            <a href="{{ route('bookings.show', $booking->booking_code) }}" class="transition-colors hover:text-gold-300">
                                                {{ $booking->apartment->title }}
                                            </a>
                                        </h2>
                                        <p class="mt-1 truncate text-[10px] font-bold uppercase tracking-[0.14em] text-ink-400">
                                            {{ $booking->apartment->city }} · {{ $booking->apartment->address }}
                                        </p>

                                        <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-3">
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-ink-400">Check-in</dt>
                                                <dd class="mt-1 text-sm text-ivory-100">{{ $booking->check_in->format('d M Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-ink-400">Check-out</dt>
                                                <dd class="mt-1 text-sm text-ivory-100">{{ $booking->check_out->format('d M Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-ink-400">Durasi</dt>
                                                <dd class="mt-1 text-sm text-ivory-100">{{ $booking->total_nights }} malam</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col gap-4 border-t border-white/10 pt-5 lg:items-end lg:border-0 lg:pt-0">
                                    <x-booking-status :status="$booking->status" />

                                    <div class="lg:text-right">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-ink-400">Total</p>
                                        <p class="mt-1 text-lg font-semibold text-ivory-100">
                                            IDR {{ number_format($booking->total_price, 0, ',', '.') }}
                                        </p>
                                    </div>

                                    @if($booking->status === BookingStatus::Pending)
                                        <a href="{{ route('bookings.show', $booking->booking_code) }}" class="gold-button w-full lg:w-auto">
                                            Bayar Sekarang
                                        </a>
                                    @else
                                        <a href="{{ route('bookings.show', $booking->booking_code) }}" class="inline-flex min-h-11 w-full items-center justify-center border border-white/15 px-5 text-[11px] font-bold uppercase tracking-[0.1em] text-ink-200 transition-colors hover:border-gold-400/50 hover:text-white lg:w-auto">
                                            Lihat detail
                                        </a>
                                    @endif
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-12">
                    {{ $bookings->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
