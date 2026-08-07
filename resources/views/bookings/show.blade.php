@extends('layouts.app')

@php use App\Enums\BookingStatus; @endphp

@section('title', 'Detail Booking #' . $booking->booking_code . ' - ApartStay')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('bookings.index') }}" class="text-xs font-semibold text-slate-400 hover:text-white flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Kembali ke Riwayat Booking
        </a>
        <span class="text-xs text-slate-500">Dibuat pada {{ $booking->created_at->format('d M Y, H:i') }}</span>
    </div>

    <!-- Invoice Header Card -->
    <div class="bg-slate-800/90 border border-slate-700 rounded-3xl p-6 sm:p-8 shadow-2xl mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-6 border-b border-slate-700">
            <div>
                <span class="text-xs text-slate-400 font-mono block">KODE RESERVASI</span>
                <h1 class="text-2xl font-extrabold text-white font-mono">{{ $booking->booking_code }}</h1>
            </div>

            <!-- Status Badge -->
            <div>
                @if($booking->status === BookingStatus::Confirmed)
                    <span class="px-4 py-2 text-sm font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-xl inline-flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Booking Terkonfirmasi (Lunas)
                    </span>
                @elseif($booking->status === BookingStatus::Pending)
                    <span class="px-4 py-2 text-sm font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-xl inline-flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span> Menunggu Pembayaran
                    </span>
                @else
                    <span class="px-4 py-2 text-sm font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30 rounded-xl inline-flex items-center gap-2">
                        {{ ucfirst($booking->status->value) }}
                    </span>
                @endif
            </div>
        </div>

        <!-- Apartment & Guest Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 py-6 border-b border-slate-700">
            <div>
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Detail Apartemen</h3>
                <div class="flex items-center gap-4">
                    <img src="{{ $booking->apartment->main_image }}" alt="{{ $booking->apartment->title }}" class="w-20 h-20 rounded-xl object-cover">
                    <div>
                        <h4 class="font-bold text-white text-base line-clamp-1">{{ $booking->apartment->title }}</h4>
                        <p class="text-xs text-slate-400 mt-1">{{ $booking->apartment->city }} • {{ $booking->apartment->address }}</p>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Informasi Tamu</h3>
                <div class="bg-slate-900/60 p-4 rounded-xl space-y-1 text-sm">
                    <p class="text-white font-bold">{{ $booking->user->name }}</p>
                    <p class="text-slate-400 text-xs">{{ $booking->user->email }} • {{ $booking->user->phone ?? '-' }}</p>
                    @if($booking->notes)
                        <p class="text-xs text-amber-300 pt-2 italic">"Catatan: {{ $booking->notes }}"</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Rincian Tanggal & Pembayaran -->
        <div class="py-6 border-b border-slate-700 space-y-3">
            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Rincian Durasi & Biaya</h3>
            <div class="flex justify-between text-sm text-slate-300">
                <span>Tanggal Check-In</span>
                <span class="font-semibold text-white">{{ $booking->check_in->format('d F Y') }} (14:00 WIB)</span>
            </div>
            <div class="flex justify-between text-sm text-slate-300">
                <span>Tanggal Check-Out</span>
                <span class="font-semibold text-white">{{ $booking->check_out->format('d F Y') }} (12:00 WIB)</span>
            </div>
            <div class="flex justify-between text-sm text-slate-300">
                <span>Tarif Sewa ({{ $booking->total_nights }} malam)</span>
                <span>Rp {{ number_format($booking->total_price, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between font-extrabold text-lg text-white pt-2 border-t border-slate-700/60">
                <span>Total Biaya</span>
                <span class="text-brand-400">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- Payment Actions Section -->
        <div class="pt-6">
            @if($booking->status === BookingStatus::Pending)
                <div class="bg-brand-500/10 border border-brand-500/30 p-6 rounded-2xl space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-bold text-white text-base">Selesaikan Pembayaran</h4>
                            <p class="text-xs text-slate-300">Gunakan Midtrans Snap Gateway atau tombol Simulasi Pembayaran untuk pengujian.</p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <!-- Midtrans Snap Button -->
                        @if($booking->payment && $booking->payment->snap_token)
                            <button id="pay-button" class="flex-1 py-3 px-6 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm rounded-xl shadow-lg transition-colors flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                Bayar Via Midtrans Gateway
                            </button>
                        @endif

                        <!-- Simulator Button (hanya untuk demo di environment lokal) -->
                        @if(app()->environment('local'))
                            <form action="{{ route('payments.simulate', $booking->booking_code) }}" method="POST" class="flex-1">
                                @csrf
                                <input type="hidden" name="status" value="settlement">
                                <button type="submit" class="w-full py-3 px-6 bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm rounded-xl transition-colors flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Simulasi Bayar Lunas (Instant)
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @elseif($booking->status === BookingStatus::Confirmed)
                <div class="bg-emerald-500/10 border border-emerald-500/30 p-5 rounded-2xl flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-emerald-400 text-base">Pembayaran Berhasil Diverifikasi!</h4>
                        <p class="text-xs text-slate-300">Tunjukkan bukti invoice reservasi ini saat kedatangan di resepsionis apartemen.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@if($booking->payment && $booking->payment->snap_token)
    <script type="text/javascript" src="{{ config('midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('midtrans.client_key') }}"></script>
    <script type="text/javascript">
        const payButton = document.getElementById('pay-button');
        if (payButton) {
            let isProcessing = false;

            payButton.onclick = function () {
                // Cegah multiple klik — hanya buka 1 popup Snap
                if (isProcessing) return;
                isProcessing = true;

                // Disable button & tampilkan loading state
                payButton.disabled = true;
                payButton.classList.add('opacity-50', 'cursor-not-allowed');
                payButton.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Memproses...';

                snap.pay('{{ $booking->payment->snap_token }}', {
                    onSuccess: function (result) {
                        window.location.reload();
                    },
                    onPending: function (result) {
                        window.location.reload();
                    },
                    onError: function (result) {
                        alert("Pembayaran gagal atau dibatalkan.");
                        // Re-enable button agar user bisa coba lagi
                        isProcessing = false;
                        payButton.disabled = false;
                        payButton.classList.remove('opacity-50', 'cursor-not-allowed');
                        payButton.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Bayar Via Midtrans Gateway';
                    },
                    onClose: function () {
                        // User menutup popup tanpa bayar — re-enable button
                        isProcessing = false;
                        payButton.disabled = false;
                        payButton.classList.remove('opacity-50', 'cursor-not-allowed');
                        payButton.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Bayar Via Midtrans Gateway';
                    }
                });
            };
        }

        // Cegah double-submit pada form simulasi pembayaran
        document.querySelectorAll('form[action*="simulate-payment"]').forEach(function(form) {
            form.addEventListener('submit', function () {
                const btn = form.querySelector('button[type="submit"]');
                if (btn.disabled) {
                    event.preventDefault();
                    return;
                }
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
                btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Memproses...';
            });
        });
    </script>
@endif
@endsection
