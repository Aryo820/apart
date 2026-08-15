@extends('layouts.app')

@php
    use App\Enums\BookingStatus;
    use App\Enums\PaymentStatus;

    // Copy Indonesia untuk status gateway; enum tetap satu sumber kebenaran.
    $paymentStatusLabel = match ($booking->payment?->status) {
        PaymentStatus::Settlement => 'Lunas',
        PaymentStatus::Pending => 'Menunggu pembayaran',
        PaymentStatus::Expire => 'Kedaluwarsa',
        PaymentStatus::Cancel => 'Dibatalkan',
        PaymentStatus::Failed => 'Gagal',
        default => 'Belum ada pembayaran',
    };
@endphp

@section('title', 'Reservasi ' . $booking->booking_code . ' — Santhosa')

@section('content')
    <section class="bg-ink-900 border-white/10 border-b">
        <div class="py-10 sm:py-12 site-container">
            <div class="mx-auto max-w-4xl">
                <nav class="flex items-center gap-2 font-bold text-[10px] text-ink-400 uppercase tracking-[0.16em]"
                    aria-label="Breadcrumb">
                    <a href="{{ route('home') }}" class="hover:text-gold-300 transition-colors">Beranda</a>
                    <span aria-hidden="true">›</span>
                    <a href="{{ route('bookings.index') }}" class="hover:text-gold-300 transition-colors">Booking Saya</a>
                    <span aria-hidden="true">›</span>
                    <span class="text-ink-200" aria-current="page">{{ $booking->booking_code }}</span>
                </nav>

                <div class="flex sm:flex-row flex-col sm:justify-between sm:items-end gap-5 mt-6">
                    <div>
                        <p class="section-eyebrow">Kode reservasi</p>
                        <h1 class="mt-3 font-mono font-bold text-white text-3xl sm:text-4xl tracking-[0.02em]">
                            {{ $booking->booking_code }}</h1>
                        <p class="mt-3 text-ink-400 text-xs">Dibuat pada {{ $booking->created_at->format('d M Y, H:i') }}
                        </p>
                    </div>

                    <x-booking-status :status="$booking->status" class="self-start sm:self-auto" />
                </div>
            </div>
        </div>
    </section>

    <section class="bg-ink-950 py-12 sm:py-16">
        <div class="site-container">
            <div class="bg-ink-900 mx-auto border border-white/10 max-w-4xl">
                <div class="gap-10 grid md:grid-cols-2 p-6 sm:p-8 border-white/10 border-b">
                    <div>
                        <h2 class="before:hidden section-eyebrow">Unit yang dipesan</h2>
                        <div class="flex items-start gap-4 mt-5">
                            <img src="{{ $booking->apartment->main_image_url }}" alt="{{ $booking->apartment->title }}"
                                width="96" height="96" class="bg-ink-800 w-20 h-20 object-cover shrink-0"
                                loading="lazy" decoding="async">
                            <div class="min-w-0">
                                <h3 class="font-display font-semibold text-white text-lg leading-snug">
                                    <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                        class="hover:text-gold-300 transition-colors">
                                        {{ $booking->apartment->title }}
                                    </a>
                                </h3>
                                <p class="mt-1.5 font-bold text-[10px] text-ink-400 uppercase tracking-[0.14em]">
                                    {{ $booking->apartment->city }} · {{ $booking->apartment->address }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="before:hidden section-eyebrow">Data pemesan</h2>
                        <dl class="space-y-3 mt-5">
                            <div>
                                <dt class="font-bold text-[10px] text-ink-400 uppercase tracking-[0.14em]">Nama</dt>
                                <dd class="mt-1 font-semibold text-ivory-100 text-sm">{{ $booking->user->name }}</dd>
                            </div>
                            <div>
                                <dt class="font-bold text-[10px] text-ink-400 uppercase tracking-[0.14em]">Kontak</dt>
                                <dd class="mt-1 text-ink-200 text-sm break-words">
                                    {{ $booking->user->email }}
                                    @if ($booking->user->phone)
                                        <span class="text-ink-400">·</span> {{ $booking->user->phone }}
                                    @endif
                                </dd>
                            </div>
                            @if ($booking->notes)
                                <div>
                                    <dt class="font-bold text-[10px] text-ink-400 uppercase tracking-[0.14em]">Catatan</dt>
                                    <dd
                                        class="mt-1 pl-3 border-gold-400/50 border-l-2 text-ink-200 text-sm italic leading-6">
                                        {{ $booking->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
                <div class="p-6 sm:p-8 border-white/10 border-b">
                    <h2 class="before:hidden section-eyebrow">Rincian durasi &amp; biaya</h2>

                    <dl class="space-y-3.5 mt-5">
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-300">Check-in</dt>
                            <dd class="font-semibold text-ivory-100">{{ $booking->check_in->format('d F Y') }} <span
                                    class="font-normal text-ink-400">(14:00 WIB)</span></dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-300">Check-out</dt>
                            <dd class="font-semibold text-ivory-100">{{ $booking->check_out->format('d F Y') }} <span
                                    class="font-normal text-ink-400">(12:00 WIB)</span></dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-300">Jumlah malam</dt>
                            <dd class="font-semibold text-ivory-100">{{ $booking->total_nights }} malam</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-300">Tarif per malam</dt>
                            <dd class="text-ivory-100">IDR {{ number_format($booking->total_price / max($booking->total_nights, 1), 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-300">Subtotal</dt>
                            <dd class="text-ivory-100">IDR {{ number_format($booking->total_price, 0, ',', '.') }}</dd>
                        </div>
                        @if ($booking->payment)
                            <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                                <dt class="text-ink-300">Status pembayaran</dt>
                                <dd class="font-semibold text-ivory-100">{{ $paymentStatusLabel }}</dd>
                            </div>
                        @endif
                        <div
                            class="flex flex-wrap justify-between items-baseline gap-x-6 gap-y-1 pt-4 border-white/10 border-t">
                            <dt class="font-bold text-[10px] text-ink-400 uppercase tracking-[0.14em]">Total biaya</dt>
                            <dd class="font-semibold text-gold-400 text-2xl">IDR
                                {{ number_format($booking->total_price, 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="p-6 sm:p-8">
                    @if ($booking->status === BookingStatus::Pending)
                        <h2 class="font-display font-semibold text-white text-xl">Selesaikan pembayaran</h2>
                        <p class="mt-2 text-ink-300 text-sm leading-6">
                            Periksa kembali unit, tanggal, dan total biaya di atas sebelum melanjutkan. Pembayaran
                            diproses secara aman melalui Midtrans. Reservasi Anda dikonfirmasi otomatis setelah
                            pembayaran diterima.
                        </p>

                        @if ($booking->payment && $booking->payment->snap_token)
                            <button type="button" id="pay-button" class="mt-6 w-full gold-button">
                                <span>Bayar Sekarang</span>
                            </button>
                            <p id="pay-error"
                                class="hidden bg-rose-500/10 mt-3 px-4 py-3 border border-rose-400/40 text-rose-200 text-xs leading-5"
                                role="alert">
                                Pembayaran gagal atau dibatalkan. Silakan coba lagi.
                            </p>
                        @else
                            <p
                                class="bg-amber-500/10 mt-6 px-4 py-3 border border-amber-400/30 text-amber-200 text-xs leading-5">
                                Sesi pembayaran belum tersedia. Muat ulang halaman atau coba beberapa saat lagi.
                            </p>
                        @endif
                    @elseif($booking->status === BookingStatus::Confirmed)
                        <div class="flex items-start gap-4 bg-emerald-500/10 p-5 border border-emerald-400/30">
                            <svg class="mt-0.5 w-6 h-6 text-emerald-400 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            <div>
                                <h2 class="font-bold text-emerald-300 text-sm uppercase tracking-[0.1em]">Pembayaran
                                    terverifikasi</h2>
                                <p class="mt-2 text-ink-200 text-sm leading-6">
                                    Tunjukkan halaman reservasi ini saat kedatangan di resepsionis apartemen.
                                </p>
                                {{-- Future improvement: unduh/cetak invoice. Belum ada dokumen
                                     invoice di backend, jadi tombolnya sengaja tidak dibuat. --}}
                            </div>
                        </div>
                    @elseif($booking->status === BookingStatus::Cancelled)
                        <div class="flex items-start gap-4 bg-rose-500/10 p-5 border border-rose-400/30">
                            <svg class="mt-0.5 w-6 h-6 text-rose-400 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            </svg>
                            <div>
                                <h2 class="font-bold text-rose-300 text-sm uppercase tracking-[0.1em]">Reservasi dibatalkan
                                </h2>
                                <p class="mt-2 text-ink-200 text-sm leading-6">
                                    Reservasi ini tidak lagi aktif. Anda dapat memesan ulang unit yang sama dari katalog.
                                </p>
                                <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                    class="mt-4 gold-button">Pesan ulang unit</a>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start gap-4 bg-white/5 p-5 border border-white/15">
                            <svg class="mt-0.5 w-6 h-6 text-ink-300 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <div>
                                <h2 class="font-bold text-ink-200 text-sm uppercase tracking-[0.1em]">Menginap selesai</h2>
                                <p class="mt-2 text-ink-300 text-sm leading-6">
                                    Terima kasih telah menginap bersama Santhosa.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($booking->payment && $booking->payment->snap_token)
        @push('scripts')
            <script
                src="{{ config('midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}"
                data-client-key="{{ config('midtrans.client_key') }}"></script>
            <script>
                const payButton = document.getElementById('pay-button');
                const payError = document.getElementById('pay-error');

                if (payButton) {
                    const idleLabel = payButton.innerHTML;
                    let isProcessing = false;

                    // .gold-button:disabled sudah menangani opacity + cursor,
                    // jadi state loading cukup lewat atribut disabled.
                    const setBusy = (busy) => {
                        isProcessing = busy;
                        payButton.disabled = busy;
                        payButton.setAttribute('aria-busy', String(busy));
                        payButton.innerHTML = busy ? '<span>Memproses...</span>' : idleLabel;
                    };

                    payButton.addEventListener('click', function() {
                        if (isProcessing) return; // hanya buka 1 popup Snap
                        payError?.classList.add('hidden');
                        setBusy(true);

                        snap.pay('{{ $booking->payment->snap_token }}', {
                            onSuccess: function() {
                                window.location.reload();
                            },
                            onPending: function() {
                                window.location.reload();
                            },
                            onError: function() {
                                payError?.classList.remove('hidden');
                                setBusy(false);
                            },
                            onClose: function() {
                                // User menutup popup tanpa bayar — bisa coba lagi.
                                setBusy(false);
                            },
                        });
                    });
                }
            </script>
        @endpush
    @endif
@endsection
