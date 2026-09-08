@extends('layouts.app')

@php use App\Enums\BookingStatus; @endphp

@section('title', 'Riwayat Booking — Santhosa')

@section('content')
    <section class="border-b border-ink-300 bg-paper-200">
        <div class="site-container py-12 sm:py-14">
            <nav class="annotation flex items-center gap-2 uppercase" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="transition-colors hover:text-blueprint-700">Beranda</a>
                <span aria-hidden="true">/</span>
                <span class="text-ink-700" aria-current="page">Booking saya</span>
            </nav>

            <div class="mt-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <h1 class="text-4xl font-bold tracking-[-0.02em] text-ink-900 sm:text-5xl">Booking saya</h1>
                @if($bookings->total() > 0)
                    <p class="text-sm leading-7 text-ink-600">
                        <span class="font-annotation font-bold text-ink-900">{{ $bookings->total() }} reservasi</span> tercatat pada akun Anda.
                    </p>
                @endif
            </div>
        </div>
    </section>

    <section class="bg-paper-100 py-12 sm:py-16">
        <div class="site-container">
            @if($bookings->isEmpty())
                <div class="empty-state graph-grid">
                    <svg class="h-10 w-10 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" />
                    </svg>
                    <h2 class="mt-4 text-2xl font-bold text-ink-900">Belum ada reservasi</h2>
                    <p class="mt-2 max-w-md text-sm leading-6 text-ink-600">
                        Reservasi yang Anda buat akan tampil di sini beserta status pembayarannya.
                    </p>
                    <a href="{{ route('apartments.index') }}" class="btn-primary mt-6">Jelajahi katalog unit</a>
                </div>
            @else
                <ul class="space-y-4">
                    @foreach($bookings as $booking)
                        <li class="border border-ink-300 bg-paper-50 transition-colors hover:border-blueprint-400">
                            <article class="flex flex-col gap-6 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex min-w-0 items-start gap-5">
                                    <div class="plate w-24 shrink-0 sm:w-32">
                                        <div class="aspect-square overflow-hidden bg-paper-300">
                                            <img
                                                src="{{ $booking->apartment->display_image_url }}"
                                                alt="{{ $booking->apartment->title }}"
                                                width="112"
                                                height="112"
                                                class="h-full w-full object-cover"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-annotation text-xs font-bold tracking-[0.04em] text-dimension-600">{{ $booking->booking_code }}</p>
                                        <h2 class="mt-1.5 text-xl font-bold leading-snug tracking-[-0.01em] text-ink-900">
                                            <a href="{{ route('bookings.show', $booking->booking_code) }}" class="transition-colors hover:text-blueprint-700">
                                                {{ $booking->apartment->title }}
                                            </a>
                                        </h2>
                                        <p class="annotation mt-1 truncate uppercase">
                                            {{ $booking->apartment->city }} · {{ $booking->apartment->address }}
                                        </p>

                                        <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-3">
                                            <div>
                                                <dt class="annotation uppercase">Check-in</dt>
                                                <dd class="mt-1 font-annotation text-sm text-ink-900">{{ $booking->check_in->format('d M Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="annotation uppercase">Check-out</dt>
                                                <dd class="mt-1 font-annotation text-sm text-ink-900">{{ $booking->check_out->format('d M Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="annotation uppercase">Durasi</dt>
                                                <dd class="mt-1 font-annotation text-sm text-ink-900">{{ $booking->total_nights }} malam</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col gap-4 border-t border-ink-200 pt-5 lg:items-end lg:border-0 lg:pt-0">
                                    {{-- Pending yang sudah melewati batas waktu ditampilkan
                                         sebagai kedaluwarsa meski command perapih statusnya
                                         belum jalan — tanggalnya memang sudah bebas. --}}
                                    @php $isExpired = $booking->status === BookingStatus::Expired || $booking->isPaymentOverdue(); @endphp
                                    <x-booking-status :status="$isExpired ? BookingStatus::Expired : $booking->status" />

                                    <div class="lg:text-right">
                                        <p class="annotation uppercase">Total</p>
                                        <p class="mt-1 font-annotation text-lg font-bold text-ink-900">
                                            IDR {{ number_format($booking->total_price, 0, ',', '.') }}
                                        </p>
                                    </div>

                                    @if($booking->status === BookingStatus::Pending && ! $isExpired)
                                        <a href="{{ route('bookings.show', $booking->booking_code) }}" class="btn-primary w-full lg:w-auto">
                                            Bayar Sekarang
                                        </a>
                                    @else
                                        <a href="{{ route('bookings.show', $booking->booking_code) }}" class="btn-secondary w-full lg:w-auto">
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
