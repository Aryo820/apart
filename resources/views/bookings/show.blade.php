@extends('layouts.app')

@php
    use App\Enums\BookingStatus;
    use App\Enums\PaymentStatus;

    $support = config('support');
    $paymentDeadline = $booking->paymentDeadlineAt();

    /*
     * Booking bisa masih berstatus Pending di DB tapi sudah lewat batas waktu:
     * tanggalnya SUDAH dibebaskan Booking::scopeBlocking, dan command
     * bookings:expire-pending baru merapikan statusnya beberapa menit kemudian.
     * Selama jeda itu halaman harus sudah bicara "kedaluwarsa" — kalau tidak,
     * kita menawarkan tombol bayar untuk tanggal yang sudah bebas dipesan
     * orang lain.
     */
    $isExpired = $booking->status === BookingStatus::Expired || $booking->isPaymentOverdue();

    /*
     * Pembayaran tercatat, tapi reservasinya tidak bisa dikonfirmasi: settlement
     * -nya sampai setelah tanggalnya dipegang tamu lain, jadi PaymentController
     * menolak mengonfirmasi dan mencatatnya untuk refund manual. Keadaan ini
     * harus punya panelnya sendiri — panel "kedaluwarsa" di bawah mengklaim
     * "tidak ada tagihan", dan di sini klaim itu tidak benar.
     */
    $paidButNotConfirmed = $booking->payment?->status === PaymentStatus::Settlement
        && ($isExpired || $booking->status === BookingStatus::Cancelled);

    /*
     * Rekonsiliasi hanya mungkin kalau ada transaksi yang bisa ditanyakan ke
     * Midtrans. Booking yang dibuat admin lewat Filament tidak punya order_id.
     */
    $canReconcile = filled($booking->payment?->order_id);

    // Tamu baru saja dikembalikan Snap lewat callback 'finish'
    // (MidtransService), jadi status di halaman ini kemungkinan besar sudah basi.
    $returnedFromGateway = request('from') === 'midtrans';

    /*
     * Rekonsiliasi otomatis saat tamu kembali dari Snap: webhook bisa belum
     * sampai (atau, di localhost, tidak akan pernah sampai), dan tanpa ini
     * halaman berbunyi "menunggu pembayaran" untuk uang yang sudah masuk.
     *
     * Tidak dijalankan bila pembayarannya sudah lunas: statusnya tidak mungkin
     * basi lagi, jadi tidak perlu memanggil API Midtrans untuk memastikannya.
     */
    $autoReconcile = $returnedFromGateway
        && $canReconcile
        && $booking->payment?->status !== PaymentStatus::Settlement;

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
                <nav class="flex items-center gap-2 font-bold text-xs text-ink-400 uppercase tracking-[0.16em]"
                    aria-label="Breadcrumb" data-no-print>
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

                    <x-booking-status :status="$isExpired ? BookingStatus::Expired : $booking->status" class="self-start sm:self-auto" />
                </div>
            </div>
        </div>
    </section>

    <section class="bg-ink-950 py-12 sm:py-16">
        <div class="site-container">
            {{-- print-sheet: kartu ini satu-satunya bukti reservasi yang bisa
                 dibawa user, jadi harus terbaca saat dicetak / disimpan PDF.
                 Aturannya ada di resources/css/app.css (@media print). --}}
            <div class="bg-ink-900 mx-auto border border-white/10 max-w-4xl print-sheet">
                <div class="gap-10 grid md:grid-cols-2 p-6 sm:p-8 border-white/10 border-b">
                    <div>
                        <h2 class="before:hidden section-eyebrow">Unit yang dipesan</h2>
                        <div class="flex items-start gap-4 mt-5">
                            <img src="{{ $booking->apartment->display_image_url }}" alt="{{ $booking->apartment->title }}"
                                width="96" height="96" class="bg-ink-800 w-20 h-20 object-cover shrink-0"
                                loading="lazy" decoding="async">
                            <div class="min-w-0">
                                <h3 class="font-display font-semibold text-white text-lg leading-snug">
                                    <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                        class="hover:text-gold-300 transition-colors">
                                        {{ $booking->apartment->title }}
                                    </a>
                                </h3>
                                <p class="mt-1.5 font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">
                                    {{ $booking->apartment->city }} · {{ $booking->apartment->address }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="before:hidden section-eyebrow">Data pemesan</h2>
                        <dl class="space-y-3 mt-5">
                            <div>
                                <dt class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Nama</dt>
                                <dd class="mt-1 font-semibold text-ivory-100 text-sm">{{ $booking->user->name }}</dd>
                            </div>
                            <div>
                                <dt class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Kontak</dt>
                                <dd class="mt-1 text-ink-200 text-sm break-words">
                                    {{ $booking->user->email }}
                                    @if ($booking->user->phone)
                                        <span class="text-ink-400">·</span> {{ $booking->user->phone }}
                                    @endif
                                </dd>
                            </div>
                            @if ($booking->notes)
                                <div>
                                    <dt class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Catatan</dt>
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
                            <dt class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Total biaya</dt>
                            <dd class="font-semibold text-gold-400 text-2xl">IDR
                                {{ number_format($booking->total_price, 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="p-6 sm:p-8">
                    @if ($booking->status === BookingStatus::Pending && ! $isExpired)
                        <h2 class="font-display font-semibold text-white text-xl">Selesaikan pembayaran</h2>
                        <p class="mt-2 text-ink-300 text-sm leading-6">
                            Periksa kembali unit, tanggal, dan total biaya di atas sebelum melanjutkan. Tanggal Anda
                            sudah dikunci, tetapi reservasi baru terkonfirmasi setelah pembayaran diterima.
                        </p>

                        {{-- Deadline dari Booking::paymentDeadlineAt (config
                             booking.payment_expiry_minutes), bukan hitungan di
                             browser: yang menentukan expired adalah server. --}}
                        <div class="bg-amber-500/10 mt-5 p-4 border border-amber-400/30">
                            <div class="flex items-start gap-3">
                                <svg class="mt-0.5 w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <div class="min-w-0">
                                    <h3 class="font-bold text-xs text-amber-300 uppercase tracking-[0.14em]">
                                        Batas waktu pembayaran
                                    </h3>
                                    <p class="mt-1.5 font-semibold text-ivory-100 text-sm">
                                        <time datetime="{{ $paymentDeadline->toIso8601String() }}">
                                            {{ $paymentDeadline->translatedFormat('l, d F Y') }} pukul
                                            {{ $paymentDeadline->format('H:i') }} WIB
                                        </time>
                                    </p>
                                    <p class="mt-2 text-amber-200/90 text-xs leading-5">
                                        Jika pembayaran belum diterima sampai waktu tersebut, reservasi ini otomatis
                                        <strong class="font-semibold">kedaluwarsa</strong> dan tanggalnya kembali
                                        tersedia untuk tamu lain. Tidak ada tagihan bila itu terjadi.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Klaim dibatasi pada yang memang terjadi di kode: Snap
                             milik Midtrans yang mengambil detail pembayaran. Tidak
                             ada klaim sertifikasi atau "100% aman". --}}
                        <ul class="space-y-2.5 mt-5 text-ink-300 text-xs leading-5">
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 bg-gold-400 w-1.5 h-1.5 shrink-0" aria-hidden="true"></span>
                                <span>Pembayaran diproses oleh <strong class="font-semibold text-ivory-100">Midtrans</strong>. Detail kartu dan kredensial pembayaran Anda dimasukkan pada halaman Midtrans, bukan pada situs ini.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 bg-gold-400 w-1.5 h-1.5 shrink-0" aria-hidden="true"></span>
                                <span>Nominal yang ditagih sama dengan total di atas — tidak ada biaya layanan tambahan.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 bg-gold-400 w-1.5 h-1.5 shrink-0" aria-hidden="true"></span>
                                <span>Status reservasi diperbarui otomatis setelah Midtrans melaporkan pembayaran. Halaman ini akan menampilkan hasilnya.</span>
                            </li>
                        </ul>

                        @if ($booking->payment && $booking->payment->snap_token)
                            <div data-no-print>
                                <button type="button" id="pay-button" class="mt-6 w-full gold-button">
                                    <span>Bayar Sekarang</span>
                                </button>
                                <p id="pay-error"
                                    class="hidden bg-rose-500/10 mt-3 px-4 py-3 border border-rose-400/40 text-rose-200 text-xs leading-5"
                                    role="alert">
                                    Pembayaran gagal atau dibatalkan. Silakan coba lagi, atau hubungi kami bila dana Anda
                                    sudah terpotong.
                                </p>
                            </div>
                        @else
                            <p
                                class="bg-amber-500/10 mt-6 px-4 py-3 border border-amber-400/30 text-amber-200 text-xs leading-5">
                                Sesi pembayaran belum tersedia. Muat ulang halaman atau coba beberapa saat lagi.
                            </p>
                        @endif

                        @if ($canReconcile)
                            {{-- Jalan keluar manual kalau notifikasi Midtrans
                                 tertunda atau tidak pernah sampai: statusnya
                                 ditanyakan langsung ke gateway, bukan menunggu.
                                 Wajib ada karena tamu bisa kembali lewat riwayat
                                 browser, tab baru, atau setelah menutup Snap —
                                 keadaan di mana callback JS tidak pernah jalan. --}}
                            <div class="mt-4 pt-4 border-white/10 border-t" data-no-print>
                                <button type="submit" form="reconcileForm"
                                    class="inline-flex justify-center items-center hover:border-gold-400/50 px-5 border border-white/15 sm:w-auto w-full min-h-11 font-bold text-xs text-ink-200 hover:text-white uppercase tracking-[0.1em] transition-colors"
                                    data-loading-label="Memeriksa...">
                                    <span>Sudah bayar? Periksa status</span>
                                </button>
                                <p class="mt-3 text-ink-400 text-xs leading-5">
                                    Status biasanya diperbarui otomatis beberapa saat setelah pembayaran. Tombol ini
                                    memeriksanya langsung ke Midtrans bila pembaruan itu belum sampai.
                                </p>
                            </div>
                        @endif

                        @if ($booking->isCancellableByGuest())
                            {{-- <dialog> native: konfirmasi dengan focus trap dan
                                 Escape yang ditangani browser sendiri, tanpa
                                 library modal dan tanpa confirm() bawaan. --}}
                            <div class="mt-5 pt-5 border-white/10 border-t" data-no-print>
                                @error('cancel')
                                    <p class="bg-rose-500/10 mb-4 px-4 py-3 border border-rose-400/40 text-rose-200 text-xs leading-5" role="alert">
                                        {{ $message }}
                                    </p>
                                @enderror

                                <button type="button" onclick="document.getElementById('cancelDialog').showModal()"
                                    class="inline-flex justify-center items-center border-white/15 hover:border-rose-400/50 px-5 border sm:w-auto w-full min-h-11 font-bold text-xs text-ink-300 hover:text-rose-200 uppercase tracking-[0.1em] transition-colors">
                                    Batalkan reservasi
                                </button>
                                <p class="mt-3 text-ink-400 text-xs leading-5">
                                    Reservasi ini belum dibayar, jadi pembatalan tidak menimbulkan tagihan maupun
                                    potongan. Setelah ada pembayaran yang masuk, pembatalan harus lewat kontak bantuan.
                                </p>

                                {{-- Tanpa utility display: <dialog> memakai display
                                     none/block dari UA stylesheet, menimpanya
                                     merusak showModal(). --}}
                                <dialog id="cancelDialog"
                                    class="bg-ink-900 backdrop:bg-ink-950/80 m-auto p-0 border border-white/15 w-[calc(100%-2rem)] max-w-md text-left">
                                    <form action="{{ route('bookings.cancel', $booking->booking_code) }}" method="POST"
                                        class="p-6 sm:p-7" data-submit-loading>
                                        @csrf
                                        @method('DELETE')

                                        <h2 class="font-display font-semibold text-white text-xl">Batalkan reservasi ini?</h2>
                                        <p class="mt-3 text-ink-300 text-sm leading-6">
                                            Reservasi <strong class="font-mono font-semibold text-ivory-100">{{ $booking->booking_code }}</strong>
                                            akan dibatalkan dan tanggal
                                            {{ $booking->check_in->format('d M') }}&ndash;{{ $booking->check_out->format('d M Y') }}
                                            kembali tersedia untuk tamu lain. Tindakan ini tidak dapat dibatalkan —
                                            Anda perlu memesan ulang bila berubah pikiran.
                                        </p>

                                        <div class="flex sm:flex-row-reverse flex-col gap-3 mt-7">
                                            <button type="submit"
                                                class="inline-flex justify-center items-center bg-rose-500/15 hover:bg-rose-500/25 px-5 border border-rose-400/50 sm:w-auto w-full min-h-11 font-bold text-xs text-rose-100 uppercase tracking-[0.1em] transition-colors"
                                                data-loading-label="Membatalkan...">
                                                <span>Ya, batalkan</span>
                                            </button>
                                            <button type="button" formnovalidate
                                                onclick="document.getElementById('cancelDialog').close()"
                                                class="inline-flex justify-center items-center hover:border-gold-400/50 px-5 border border-white/15 sm:w-auto w-full min-h-11 font-bold text-xs text-ink-200 hover:text-white uppercase tracking-[0.1em] transition-colors">
                                                Tetap simpan
                                            </button>
                                        </div>
                                    </form>
                                </dialog>
                            </div>
                        @endif
                    @elseif($booking->status === BookingStatus::Confirmed)
                        <div class="bg-emerald-500/10 p-5 sm:p-6 border border-emerald-400/30">
                            <div class="flex items-start gap-4">
                                <svg class="mt-0.5 w-6 h-6 text-emerald-400 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                <div class="min-w-0">
                                    <h2 class="font-bold text-emerald-300 text-sm uppercase tracking-[0.1em]">
                                        Reservasi terkonfirmasi
                                    </h2>
                                    <p class="mt-2 text-ink-200 text-sm leading-6">
                                        Pembayaran Anda sudah diterima dan unit ini resmi dipesan untuk tanggal di bawah.
                                    </p>

                                    {{-- Ringkasan singkat di dalam panel: rinciannya
                                         sudah ada di atas, tapi blok ini harus utuh
                                         sendiri saat dicetak atau di-screenshot. --}}
                                    <dl class="gap-x-8 gap-y-3 grid sm:grid-cols-2 mt-5 pt-5 border-emerald-400/20 border-t text-sm">
                                        <div>
                                            <dt class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Kode reservasi</dt>
                                            <dd class="mt-1 font-mono font-bold text-ivory-100">{{ $booking->booking_code }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Unit</dt>
                                            <dd class="mt-1 text-ivory-100">{{ $booking->apartment->title }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Menginap</dt>
                                            <dd class="mt-1 text-ivory-100">
                                                {{ $booking->check_in->format('d M Y') }} &ndash;
                                                {{ $booking->check_out->format('d M Y') }}
                                                <span class="text-ink-400">({{ $booking->total_nights }} malam)</span>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Total dibayar</dt>
                                            <dd class="mt-1 font-semibold text-ivory-100">
                                                IDR {{ number_format($booking->total_price, 0, ',', '.') }}
                                                <span class="text-ink-400">· {{ $paymentStatusLabel }}</span>
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <div class="mt-6 pt-5 border-emerald-400/20 border-t">
                                <h3 class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Langkah selanjutnya</h3>
                                <ol class="space-y-1.5 mt-3 text-ink-200 text-sm leading-6 list-decimal list-inside">
                                    <li>Simpan atau cetak halaman ini sebagai bukti reservasi.</li>
                                    <li>Datang pada tanggal check-in mulai pukul 14.00 WIB.</li>
                                    <li>Tunjukkan kode reservasi <strong class="font-mono">{{ $booking->booking_code }}</strong> beserta identitas Anda di resepsionis.</li>
                                    <li>Check-out paling lambat pukul 12.00 WIB pada tanggal terakhir.</li>
                                </ol>

                                <div class="flex sm:flex-row flex-col gap-3 mt-6" data-no-print>
                                    {{-- window.print() = fitur browser bawaan; tidak perlu
                                         library PDF hanya untuk menyimpan bukti. --}}
                                    <button type="button" onclick="window.print()" class="sm:w-auto w-full gold-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                d="M6 9V4h12v5M6 18H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-1M6 14h12v6H6v-6Z" />
                                        </svg>
                                        <span>Cetak / simpan PDF</span>
                                    </button>
                                    <a href="{{ route('bookings.index') }}"
                                        class="inline-flex justify-center items-center border-white/15 hover:border-gold-400/50 px-5 border sm:w-auto w-full min-h-11 font-bold text-xs text-ink-200 hover:text-white uppercase tracking-[0.1em] transition-colors">
                                        Lihat semua reservasi
                                    </a>
                                </div>
                            </div>
                        </div>
                    @elseif ($paidButNotConfirmed)
                        <div class="flex items-start gap-4 bg-amber-500/10 p-5 sm:p-6 border border-amber-400/30">
                            <svg class="mt-0.5 w-6 h-6 text-amber-400 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            </svg>
                            <div class="min-w-0">
                                <h2 class="font-bold text-amber-300 text-sm uppercase tracking-[0.1em]">
                                    Pembayaran diterima, reservasi perlu ditinjau
                                </h2>
                                <p class="mt-2 text-ink-200 text-sm leading-6">
                                    Pembayaran Anda tercatat, tetapi tanggal
                                    {{ $booking->check_in->format('d M') }}&ndash;{{ $booking->check_out->format('d M Y') }}
                                    sudah dipesan tamu lain sebelum pembayaran ini sampai ke sistem kami, jadi reservasi
                                    ini tidak dapat dikonfirmasi.
                                </p>
                                <p class="mt-3 text-ink-200 text-sm leading-6">
                                    Tim kami akan menindaklanjuti pengembalian dana Anda. Hubungi kami dengan kode
                                    <strong class="font-mono font-semibold text-ivory-100">{{ $booking->booking_code }}</strong>
                                    bila Anda ingin mempercepat prosesnya atau memilih tanggal lain.
                                </p>

                                <div class="flex sm:flex-row flex-col gap-3 mt-5" data-no-print>
                                    <a href="mailto:{{ $support['email'] }}?subject={{ rawurlencode('Pengembalian dana reservasi ' . $booking->booking_code) }}"
                                        class="sm:w-auto w-full gold-button">Hubungi bantuan</a>
                                    <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                        class="inline-flex justify-center items-center hover:border-gold-400/50 px-5 border border-white/15 sm:w-auto w-full min-h-11 font-bold text-xs text-ink-200 hover:text-white uppercase tracking-[0.1em] transition-colors">
                                        Pilih tanggal lain
                                    </a>
                                </div>
                            </div>
                        </div>
                    @elseif ($isExpired)
                        <div class="flex items-start gap-4 bg-white/5 p-5 border border-white/15">
                            <svg class="mt-0.5 w-6 h-6 text-ink-300 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <div class="min-w-0">
                                <h2 class="font-bold text-ink-200 text-sm uppercase tracking-[0.1em]">Reservasi kedaluwarsa</h2>
                                <p class="mt-2 text-ink-300 text-sm leading-6">
                                    Batas waktu pembayaran
                                    @if ($paymentDeadline)
                                        (<time datetime="{{ $paymentDeadline->toIso8601String() }}">{{ $paymentDeadline->translatedFormat('d F Y, H:i') }} WIB</time>)
                                    @endif
                                    terlewati tanpa pembayaran yang diterima, jadi reservasi ini ditutup otomatis.
                                    <strong class="font-semibold text-ivory-100">Tidak ada tagihan</strong> atas
                                    reservasi ini.
                                </p>
                                <p class="mt-3 text-ink-300 text-sm leading-6">
                                    Tanggal {{ $booking->check_in->format('d M') }}&ndash;{{ $booking->check_out->format('d M Y') }}
                                    sudah dilepas kembali ke kalender unit. Tanggal tersebut bisa dipesan ulang selama
                                    belum diambil tamu lain.
                                </p>

                                <div class="flex sm:flex-row flex-col gap-3 mt-5" data-no-print>
                                    <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                        class="sm:w-auto w-full gold-button">Pesan ulang unit ini</a>
                                    <a href="{{ route('apartments.index') }}"
                                        class="inline-flex justify-center items-center hover:border-gold-400/50 px-5 border border-white/15 sm:w-auto w-full min-h-11 font-bold text-xs text-ink-200 hover:text-white uppercase tracking-[0.1em] transition-colors">
                                        Lihat unit lain
                                    </a>
                                </div>

                                @if ($canReconcile)
                                    {{-- Kasus paling mahal: tamu membayar sebelum
                                         batas waktu, notifikasinya hilang, dan
                                         reservasinya tampak kedaluwarsa. Tanpa
                                         tombol ini uang itu tidak punya jalan
                                         untuk ditemukan dari sisi tamu. --}}
                                    <p class="mt-5 pt-4 border-white/10 border-t text-ink-400 text-xs leading-5" data-no-print>
                                        Merasa sudah membayar reservasi ini?
                                        <button type="submit" form="reconcileForm"
                                            class="font-bold text-gold-400 hover:text-gold-200 underline underline-offset-2 transition-colors"
                                            data-loading-label="Memeriksa...">
                                            <span>Periksa status pembayaran</span>
                                        </button>
                                    </p>
                                @endif
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
                                    Reservasi ini tidak lagi aktif dan tanggalnya sudah dilepas kembali ke kalender
                                    unit. Anda dapat memesan ulang unit yang sama selama tanggalnya masih kosong.
                                </p>
                                <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                    class="mt-4 gold-button">Pesan ulang unit</a>

                                @if ($canReconcile)
                                    {{-- Pembayaran yang ditolak boleh dicoba ulang
                                         pada order_id yang sama, jadi booking yang
                                         terlanjur dibatalkan bisa punya pembayaran
                                         berhasil yang notifikasinya belum sampai. --}}
                                    <p class="mt-5 pt-4 border-white/10 border-t text-ink-400 text-xs leading-5" data-no-print>
                                        Merasa sudah membayar reservasi ini?
                                        <button type="submit" form="reconcileForm"
                                            class="font-bold text-gold-400 hover:text-gold-200 underline underline-offset-2 transition-colors"
                                            data-loading-label="Memeriksa...">
                                            <span>Periksa status pembayaran</span>
                                        </button>
                                    </p>
                                @endif
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
                                    Terima kasih telah menginap bersama {{ $support['business_name'] }}.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Jalur bantuan tampil di semua status: masalah pembayaran,
                     pembatalan, dan pertanyaan saat menginap sama-sama butuh
                     kontak, dan belum ada email konfirmasi yang memuatnya. --}}
                <div class="bg-ink-950/40 p-6 sm:p-8 border-white/10 border-t">
                    <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-5">
                        <div class="min-w-0">
                            <h2 class="font-bold text-xs text-ink-400 uppercase tracking-[0.14em]">Butuh bantuan?</h2>
                            <p class="mt-2 text-ink-300 text-sm leading-6">
                                Hubungi kami dan sertakan kode reservasi
                                <strong class="font-mono font-semibold text-ivory-100">{{ $booking->booking_code }}</strong>.
                                {{ $support['hours'] }}.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 shrink-0 text-sm">
                            <a href="mailto:{{ $support['email'] }}?subject={{ rawurlencode('Bantuan reservasi ' . $booking->booking_code) }}"
                                class="inline-flex items-center gap-2 min-h-11 font-semibold text-gold-400 hover:text-gold-200 break-all transition-colors">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Zm0 .5 9 6 9-6" />
                                </svg>
                                {{ $support['email'] }}
                            </a>
                            <a href="tel:{{ $support['phone_tel'] }}"
                                class="inline-flex items-center gap-2 min-h-11 font-semibold text-gold-400 hover:text-gold-200 transition-colors">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M4 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L14 13l5 2v4a1 1 0 0 1-1.1 1A15 15 0 0 1 3 5.1 1 1 0 0 1 4 4Z" />
                                </svg>
                                {{ $support['phone'] }}
                            </a>
                        </div>
                    </div>
                    <p class="mt-4 text-ink-400 text-xs leading-5" data-no-print>
                        Baca <a href="{{ route('legal.terms') }}" class="text-ink-200 hover:text-gold-300 underline underline-offset-2 transition-colors">Syarat &amp; Ketentuan</a>
                        untuk ketentuan pembayaran dan pembatalan.
                    </p>
                </div>
            </div>

            @if ($canReconcile)
                {{-- Form kosong di luar panel status: tombol "periksa status" ada di
                     beberapa panel yang saling eksklusif dan semuanya menunjuk ke
                     sini lewat atribut form="reconcileForm". Native HTML, jadi
                     tombolnya tetap bekerja tanpa JS. --}}
                <form id="reconcileForm" action="{{ route('bookings.reconcile', $booking->booking_code) }}"
                    method="POST" class="hidden" data-submit-loading>
                    @csrf
                </form>
            @endif
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
                const reconcileForm = document.getElementById('reconcileForm');

                /*
                 * Reload saja tidak cukup: status di halaman ini ditulis webhook,
                 * dan webhook bisa belum sampai saat popup Snap tertutup. Submit
                 * form rekonsiliasi supaya statusnya ditanyakan langsung ke
                 * Midtrans; reload hanya dipakai bila form itu tidak ada.
                 */
                const refreshStatus = () => reconcileForm ? reconcileForm.requestSubmit() : window.location.reload();

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
                                refreshStatus();
                            },
                            onPending: function() {
                                refreshStatus();
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

    @if ($autoReconcile)
        @push('scripts')
            <script>
                /*
                 * Tamu baru kembali dari Snap (callback 'finish'), jadi status di
                 * halaman ini ditulis sebelum pembayarannya dilaporkan. Formnya
                 * di-submit sekali di sini — redirect sesudahnya membuang
                 * ?from=midtrans, jadi tidak berulang.
                 */
                document.getElementById('reconcileForm')?.requestSubmit();
            </script>
        @endpush
    @endif
@endsection
