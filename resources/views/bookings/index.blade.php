@extends('layouts.app')

@php use App\Enums\BookingStatus; @endphp

@section('title', 'Riwayat Booking - ApartStay')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-white tracking-tight">Riwayat Pemesanan Anda</h1>
        <p class="text-slate-400 text-sm mt-1">Daftar reservasi apartemen dan status pembayaran Anda.</p>
    </div>

    @if($bookings->isEmpty())
        <div class="bg-slate-800/50 border border-slate-800 rounded-2xl p-12 text-center">
            <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <h3 class="text-xl font-bold text-white mb-2">Belum Ada Pemesanan</h3>
            <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">Anda belum pernah melakukan pemesanan apartemen. Mulai cari apartemen impian Anda sekarang!</p>
            <a href="{{ route('apartments.index') }}" class="px-6 py-3 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm rounded-xl inline-block transition-colors shadow-lg shadow-brand-500/20">
                Jelajahi Apartemen
            </a>
        </div>
    @else
        <div class="space-y-6">
            @foreach($bookings as $booking)
                <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 hover:border-slate-600 transition-colors">
                    
                    <!-- Left: Apartment Info -->
                    <div class="flex items-center gap-5">
                        <img src="{{ $booking->apartment->main_image }}" alt="{{ $booking->apartment->title }}" class="w-24 h-24 rounded-xl object-cover flex-shrink-0">
                        <div>
                            <span class="text-xs font-mono font-bold text-slate-400 block mb-1">#{{ $booking->booking_code }}</span>
                            <h3 class="font-bold text-lg text-white line-clamp-1">{{ $booking->apartment->title }}</h3>
                            <p class="text-xs text-slate-400 mb-2">{{ $booking->apartment->city }} • {{ $booking->apartment->address }}</p>
                            
                            <div class="flex items-center gap-4 text-xs text-slate-300">
                                <span>Check-in: <strong class="text-white">{{ $booking->check_in->format('d M Y') }}</strong></span>
                                <span>Check-out: <strong class="text-white">{{ $booking->check_out->format('d M Y') }}</strong></span>
                                <span>({{ $booking->total_nights }} malam)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Status & Action -->
                    <div class="flex flex-col md:items-end gap-3 w-full md:w-auto pt-4 md:pt-0 border-t md:border-t-0 border-slate-700">
                        <!-- Status Badge -->
                        @if($booking->status === BookingStatus::Confirmed)
                            <span class="px-3 py-1 text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-full inline-flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Confirmed / Lunas
                            </span>
                        @elseif($booking->status === BookingStatus::Pending)
                            <span class="px-3 py-1 text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-full inline-flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> Menunggu Pembayaran
                            </span>
                        @elseif($booking->status === BookingStatus::Cancelled)
                            <span class="px-3 py-1 text-xs font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30 rounded-full inline-flex items-center gap-1">
                                Dibatalkan
                            </span>
                        @else
                            <span class="px-3 py-1 text-xs font-bold bg-slate-700 text-slate-300 rounded-full">
                                {{ ucfirst($booking->status->value) }}
                            </span>
                        @endif

                        <div class="text-lg font-extrabold text-brand-400">
                            Rp {{ number_format($booking->total_price, 0, ',', '.') }}
                        </div>

                        <a href="{{ route('bookings.show', $booking->booking_code) }}" class="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-xl text-center transition-colors">
                            @if($booking->status === BookingStatus::Pending)
                                Bayar Sekarang
                            @else
                                Detail Invoice
                            @endif
                        </a>
                    </div>

                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $bookings->links() }}
        </div>
    @endif
</div>
@endsection
