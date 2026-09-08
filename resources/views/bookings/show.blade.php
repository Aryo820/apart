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
    <section class="border-b border-ink-300 bg-paper-200">
        <div class="site-container py-10 sm:py-12">
            <div class="mx-auto max-w-4xl">
                <nav class="annotation flex items-center gap-2 uppercase" aria-label="Breadcrumb" data-no-print>
                    <a href="{{ route('home') }}" class="transition-colors hover:text-blueprint-700">Beranda</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('bookings.index') }}" class="transition-colors hover:text-blueprint-700">Booking saya</a>
                    <span aria-hidden="true">/</span>
                    <span class="text-ink-700" aria-current="page">{{ $booking->booking_code }}</span>
                </nav>

                <div class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="annotation uppercase">Kode reservasi</p>
                        <h1 class="mt-2 font-annotation text-3xl font-bold tracking-[0.01em] text-ink-900 sm:text-4xl">
                            {{ $booking->booking_code }}</h1>
                        <p class="mt-3 font-annotation text-xs text-ink-500">Diterbitkan {{ $booking->created_at->format('d M Y, H:i') }}
                        </p>
                    </div>

                    <x-booking-status :status="$isExpired ? BookingStatus::Expired : $booking->status" class="self-start sm:self-auto" />
                </div>
            </div>
        </div>
    </section>

    <section class="bg-paper-100 py-12 sm:py-16">
        <div class="site-container">
            {{-- print-sheet: kartu ini satu-satunya bukti reservasi yang bisa
                 dibawa user, jadi harus terbaca saat dicetak / disimpan PDF.
                 Aturannya ada di resources/css/app.css (@media print). --}}
            <div class="print-sheet mx-auto max-w-4xl border border-ink-300 bg-paper-50">
                <div class="grid gap-10 border-b border-ink-300 p-6 sm:p-8 md:grid-cols-2">
                    <div>
                        <h2 class="annotation annotation--strong uppercase">Unit yang dipesan</h2>
                        <div class="mt-5 flex items-start gap-4">
                            <div class="plate w-24 shrink-0">
                                <div class="aspect-square overflow-hidden bg-paper-300">
                                    <img src="{{ $booking->apartment->display_image_url }}" alt="{{ $booking->apartment->title }}"
                                        width="96" height="96" class="h-full w-full object-cover"
                                        loading="lazy" decoding="async">
                                </div>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-lg font-bold leading-snug tracking-[-0.01em] text-ink-900">
                                    <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                        class="transition-colors hover:text-blueprint-700">
                                        {{ $booking->apartment->title }}
                                    </a>
                                </h3>
                                <p class="annotation mt-1.5 uppercase">
                                    {{ $booking->apartment->city }} · {{ $booking->apartment->address }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="annotation annotation--strong uppercase">Data pemesan</h2>
                        <dl class="mt-5 space-y-3">
                            <div>
                                <dt class="annotation uppercase">Nama</dt>
                                <dd class="mt-1 text-sm font-semibold text-ink-900">{{ $booking->user->name }}</dd>
                            </div>
                            <div>
                                <dt class="annotation uppercase">Kontak</dt>
                                <dd class="mt-1 break-words text-sm text-ink-700">
                                    {{ $booking->user->email }}
                                    @if ($booking->user->phone)
                                        <span class="text-ink-400">·</span> {{ $booking->user->phone }}
                                    @endif
                                </dd>
                            </div>
                            @if ($booking->notes)
                                <div>
                                    <dt class="annotation uppercase">Catatan</dt>
                                    <dd class="mt-1 border-l-2 border-blueprint-400 pl-3 text-sm italic leading-6 text-ink-700">
                                        {{ $booking->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="border-b border-ink-300 p-6 sm:p-8">
                    <h2 class="annotation annotation--strong uppercase">Rincian durasi &amp; biaya</h2>

                    <dl class="mt-5 space-y-3.5">
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-600">Check-in</dt>
                            <dd class="font-annotation font-semibold text-ink-900">{{ $booking->check_in->format('d F Y') }} <span
                                    class="font-normal text-ink-500">(14:00 WIB)</span></dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-600">Check-out</dt>
                            <dd class="font-annotation font-semibold text-ink-900">{{ $booking->check_out->format('d F Y') }} <span
                                    class="font-normal text-ink-500">(12:00 WIB)</span></dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-600">Jumlah malam</dt>
                            <dd class="font-annotation font-semibold text-ink-900">{{ $booking->total_nights }} malam</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-600">Tarif per malam</dt>
                            <dd class="font-annotation text-ink-900">IDR {{ number_format($booking->total_price / max($booking->total_nights, 1), 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                            <dt class="text-ink-600">Subtotal</dt>
                            <dd class="font-annotation text-ink-900">IDR {{ number_format($booking->total_price, 0, ',', '.') }}</dd>
                        </div>
                        @if ($booking->payment)
                            <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 text-sm">
                                <dt class="text-ink-600">Status pembayaran</dt>
                                <dd class="font-annotation font-semibold text-ink-900">{{ $paymentStatusLabel }}</dd>
                            </div>
                        @endif
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 border-t border-ink-300 pt-4">
                            <dt class="annotation font-bold uppercase">Total biaya</dt>
                            <dd class="font-annotation text-2xl font-bold text-dimension-600">IDR
                                {{ number_format($booking->total_price, 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="p-6 sm:p-8">
                    @if ($booking->status === BookingStatus::Pending && ! $isExpired)
                        <h2 class="text-xl font-bold tracking-[-0.015em] text-ink-900">Selesaikan pembayaran</h2>
                        <p class="mt-2 text-sm leading-6 text-ink-600">
                            Periksa kembali unit, tanggal, dan total biaya di atas sebelum melanjutkan. Tanggal Anda
                            sudah dikunci, tetapi reservasi baru terkonfirmasi setelah pembayaran diterima.
                        </p>

                        {{-- Deadline dari Booking::paymentDeadlineAt (config
                             booking.payment_expiry_minutes), bukan hitungan di
                             browser: yang menentukan expired adalah server. --}}
                        <div class="mt-5 border border-amber-600/40 bg-amber-50 p-4">
                            <div class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <div class="min-w-0">
                                    <h3 class="font-annotation text-xs font-bold uppercase tracking-[0.08em] text-amber-800">
                                        Batas waktu pembayaran
                                    </h3>
                                    <p class="mt-1.5 text-sm font-semibold text-ink-900">
                                        <time datetime="{{ $paymentDeadline->toIso8601String() }}">
                                            {{ $paymentDeadline->translatedFormat('l, d F Y') }} pukul
                                            {{ $paymentDeadline->format('H:i') }} WIB
                                        </time>
                                    </p>
                                    <p class="mt-2 text-xs leading-5 text-amber-900">
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
                        <ul class="mt-5 space-y-2.5 text-xs leading-5 text-ink-600">
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 bg-blueprint-600" aria-hidden="true"></span>
                                <span>Pembayaran diproses oleh <strong class="font-semibold text-ink-900">Midtrans</strong>. Detail kartu dan kredensial pembayaran Anda dimasukkan pada halaman Midtrans, bukan pada situs ini.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 bg-blueprint-600" aria-hidden="true"></span>
                                <span>Nominal yang ditagih sama dengan total di atas — tidak ada biaya layanan tambahan.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 bg-blueprint-600" aria-hidden="true"></span>
                                <span>Status reservasi diperbarui otomatis setelah Midtrans melaporkan pembayaran. Halaman ini akan menampilkan hasilnya.</span>
                            </li>
                        </ul>

                        @if ($booking->payment && $booking->payment->snap_token)
                            <div data-no-print>
                                <button type="button" id="pay-button" class="btn-primary mt-6 w-full">
                                    <span>Bayar Sekarang</span>
                                </button>
                                <p id="pay-error"
                                    class="mt-3 hidden border border-dimension-500 bg-dimension-50 px-4 py-3 text-xs leading-5 text-dimension-700"
                                    role="alert">
                                    Pembayaran gagal atau dibatalkan. Silakan coba lagi, atau hubungi kami bila dana Anda
                                    sudah terpotong.
                                </p>
                            </div>
                        @else
                            <p
                                class="mt-6 border border-amber-600/40 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">
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
                            <div class="mt-4 border-t border-ink-200 pt-4" data-no-print>
                                <button type="submit" form="reconcileForm"
                                    class="btn-secondary w-full sm:w-auto"
                                    data-loading-label="Memeriksa...">
                                    <span>Sudah bayar? Periksa status</span>
                                </button>
                                <p class="mt-3 text-xs leading-5 text-ink-500">
                                    Status biasanya diperbarui otomatis beberapa saat setelah pembayaran. Tombol ini
                                    memeriksanya langsung ke Midtrans bila pembaruan itu belum sampai.
                                </p>
                            </div>
                        @endif

                        @if ($booking->isCancellableByGuest())
                            {{-- <dialog> native: konfirmasi dengan focus trap dan
                                 Escape yang ditangani browser sendiri, tanpa
                                 library modal dan tanpa confirm() bawaan. --}}
                            <div class="mt-5 border-t border-ink-200 pt-5" data-no-print>
                                @error('cancel')
                                    <p class="mb-4 border border-dimension-500 bg-dimension-50 px-4 py-3 text-xs leading-5 text-dimension-700" role="alert">
                                        {{ $message }}
                                    </p>
                                @enderror

                                <button type="button" onclick="document.getElementById('cancelDialog').showModal()"
                                    class="btn-secondary w-full border-dimension-400 text-dimension-700 hover:border-dimension-600 hover:text-dimension-700 sm:w-auto">
                                    Batalkan reservasi
                                </button>
                                <p class="mt-3 text-xs leading-5 text-ink-500">
                                    Reservasi ini belum dibayar, jadi pembatalan tidak menimbulkan tagihan maupun
                                    potongan. Setelah ada pembayaran yang masuk, pembatalan harus lewat kontak bantuan.
                                </p>

                                {{-- Tanpa utility display: <dialog> memakai display
                                     none/block dari UA stylesheet, menimpanya
                                     merusak showModal(). --}}
                                <dialog id="cancelDialog"
                                    class="m-auto w-[calc(100%-2rem)] max-w-md border border-ink-300 bg-paper-50 p-0 text-left backdrop:bg-ink-900/50">
                                    <form action="{{ route('bookings.cancel', $booking->booking_code) }}" method="POST"
                                        class="p-6 sm:p-7" data-submit-loading>
                                        @csrf
                                        @method('DELETE')

                                        <h2 class="text-xl font-bold tracking-[-0.015em] text-ink-900">Batalkan reservasi ini?</h2>
                                        <p class="mt-3 text-sm leading-6 text-ink-600">
                                            Reservasi <strong class="font-annotation font-semibold text-ink-900">{{ $booking->booking_code }}</strong>
                                            akan dibatalkan dan tanggal
                                            {{ $booking->check_in->format('d M') }}&ndash;{{ $booking->check_out->format('d M Y') }}
                                            kembali tersedia untuk tamu lain. Tindakan ini tidak dapat dibatalkan —
                                            Anda perlu memesan ulang bila berubah pikiran.
                                        </p>

                                        <div class="mt-7 flex flex-col gap-3 sm:flex-row-reverse">
                                            <button type="submit"
                                                class="btn-primary w-full border-dimension-700 bg-dimension-600 hover:border-dimension-700 hover:bg-dimension-700 sm:w-auto"
                                                data-loading-label="Membatalkan...">
                                                <span>Ya, batalkan</span>
                                            </button>
                                            <button type="button" formnovalidate
                                                onclick="document.getElementById('cancelDialog').close()"
                                                class="btn-secondary w-full sm:w-auto">
                                                Tetap simpan
                                            </button>
                                        </div>
                                    </form>
                                </dialog>
                            </div>
                        @endif
                    @elseif($booking->status === BookingStatus::Confirmed)
                        <div class="border border-emerald-700/40 bg-emerald-50 p-5 sm:p-6">
                            <div class="flex items-start gap-4">
                                <svg class="mt-0.5 h-6 w-6 shrink-0 text-emerald-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                <div class="min-w-0">
                                    <h2 class="font-annotation text-sm font-bold uppercase tracking-[0.06em] text-emerald-800">
                                        Reservasi terkonfirmasi
                                    </h2>
                                    <p class="mt-2 text-sm leading-6 text-ink-700">
                                        Pembayaran Anda sudah diterima dan unit ini resmi dipesan untuk tanggal di bawah.
                                    </p>

                                    {{-- Ringkasan singkat di dalam panel: rinciannya
                                         sudah ada di atas, tapi blok ini harus utuh
                                         sendiri saat dicetak atau di-screenshot. --}}
                                    <dl class="mt-5 grid gap-x-8 gap-y-3 border-t border-emerald-700/20 pt-5 sm:grid-cols-2">
                                        <div>
                                            <dt class="annotation uppercase">Kode reservasi</dt>
                                            <dd class="mt-1 font-annotation font-bold text-ink-900">{{ $booking->booking_code }}</dd>
                                        </div>
                                        <div>
                                            <dt class="annotation uppercase">Unit</dt>
                                            <dd class="mt-1 text-sm text-ink-900">{{ $booking->apartment->title }}</dd>
                                        </div>
                                        <div>
                                            <dt class="annotation uppercase">Menginap</dt>
                                            <dd class="mt-1 font-annotation text-sm text-ink-900">
                                                {{ $booking->check_in->format('d M Y') }} &ndash;
                                                {{ $booking->check_out->format('d M Y') }}
                                                <span class="text-ink-500">({{ $booking->total_nights }} malam)</span>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="annotation uppercase">Total dibayar</dt>
                                            <dd class="mt-1 font-annotation text-sm font-semibold text-ink-900">
                                                IDR {{ number_format($booking->total_price, 0, ',', '.') }}
                                                <span class="font-normal text-ink-500">· {{ $paymentStatusLabel }}</span>
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <div class="mt-6 border-t border-emerald-700/20 pt-5">
                                <h3 class="annotation annotation--strong uppercase">Langkah selanjutnya</h3>
                                <ol class="mt-3 list-inside list-decimal space-y-1.5 text-sm leading-6 text-ink-700">
                                    <li>Simpan atau cetak halaman ini sebagai bukti reservasi.</li>
                                    <li>Datang pada tanggal check-in mulai pukul 14.00 WIB.</li>
                                    <li>Tunjukkan kode reservasi <strong class="font-annotation">{{ $booking->booking_code }}</strong> beserta identitas Anda di resepsionis.</li>
                                    <li>Check-out paling lambat pukul 12.00 WIB pada tanggal terakhir.</li>
                                </ol>

                                <div class="mt-6 flex flex-col gap-3 sm:flex-row" data-no-print>
                                    {{-- window.print() = fitur browser bawaan; tidak perlu
                                         library PDF hanya untuk menyimpan bukti. --}}
                                    <button type="button" onclick="window.print()" class="btn-primary w-full sm:w-auto">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                d="M6 9V4h12v5M6 18H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-1M6 14h12v6H6v-6Z" />
                                        </svg>
                                        <span>Cetak / simpan PDF</span>
                                    </button>
                                    <a href="{{ route('bookings.index') }}"
                                        class="btn-secondary w-full sm:w-auto">
                                        Lihat semua reservasi
                                    </a>
                                </div>
                            </div>
                        </div>
                    @elseif ($paidButNotConfirmed)
                        <div class="flex items-start gap-4 border border-amber-600/40 bg-amber-50 p-5 sm:p-6">
                            <svg class="mt-0.5 h-6 w-6 shrink-0 text-amber-700" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            </svg>
                            <div class="min-w-0">
                                <h2 class="font-annotation text-sm font-bold uppercase tracking-[0.06em] text-amber-800">
                                    Pembayaran diterima, reservasi perlu ditinjau
                                </h2>
                                <p class="mt-2 text-sm leading-6 text-ink-700">
                                    Pembayaran Anda tercatat, tetapi tanggal
                                    {{ $booking->check_in->format('d M') }}&ndash;{{ $booking->check_out->format('d M Y') }}
                                    sudah dipesan tamu lain sebelum pembayaran ini sampai ke sistem kami, jadi reservasi
                                    ini tidak dapat dikonfirmasi.
                                </p>
                                <p class="mt-3 text-sm leading-6 text-ink-700">
                                    Tim kami akan menindaklanjuti pengembalian dana Anda. Hubungi kami dengan kode
                                    <strong class="font-annotation font-semibold text-ink-900">{{ $booking->booking_code }}</strong>
                                    bila Anda ingin mempercepat prosesnya atau memilih tanggal lain.
                                </p>

                                <div class="mt-5 flex flex-col gap-3 sm:flex-row" data-no-print>
                                    <a href="mailto:{{ $support['email'] }}?subject={{ rawurlencode('Pengembalian dana reservasi ' . $booking->booking_code) }}"
                                        class="btn-primary w-full sm:w-auto">Hubungi bantuan</a>
                                    <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                        class="btn-secondary w-full sm:w-auto">
                                        Pilih tanggal lain
                                    </a>
                                </div>
                            </div>
                        </div>
                    @elseif ($isExpired)
                        <div class="flex items-start gap-4 border border-ink-300 bg-paper-100 p-5">
                            <svg class="mt-0.5 h-6 w-6 shrink-0 text-ink-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <div class="min-w-0">
                                <h2 class="font-annotation text-sm font-bold uppercase tracking-[0.06em] text-ink-700">Reservasi kedaluwarsa</h2>
                                <p class="mt-2 text-sm leading-6 text-ink-600">
                                    Batas waktu pembayaran
                                    @if ($paymentDeadline)
                                        (<time datetime="{{ $paymentDeadline->toIso8601String() }}">{{ $paymentDeadline->translatedFormat('d F Y, H:i') }} WIB</time>)
                                    @endif
                                    terlewati tanpa pembayaran yang diterima, jadi reservasi ini ditutup otomatis.
                                    <strong class="font-semibold text-ink-900">Tidak ada tagihan</strong> atas
                                    reservasi ini.
                                </p>
                                <p class="mt-3 text-sm leading-6 text-ink-600">
                                    Tanggal {{ $booking->check_in->format('d M') }}&ndash;{{ $booking->check_out->format('d M Y') }}
                                    sudah dilepas kembali ke kalender unit. Tanggal tersebut bisa dipesan ulang selama
                                    belum diambil tamu lain.
                                </p>

                                <div class="mt-5 flex flex-col gap-3 sm:flex-row" data-no-print>
                                    <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                        class="btn-primary w-full sm:w-auto">Pesan ulang unit ini</a>
                                    <a href="{{ route('apartments.index') }}"
                                        class="btn-secondary w-full sm:w-auto">
                                        Lihat unit lain
                                    </a>
                                </div>

                                @if ($canReconcile)
                                    {{-- Kasus paling mahal: tamu membayar sebelum
                                         batas waktu, notifikasinya hilang, dan
                                         reservasinya tampak kedaluwarsa. Tanpa
                                         tombol ini uang itu tidak punya jalan
                                         untuk ditemukan dari sisi tamu. --}}
                                    <p class="mt-5 border-t border-ink-200 pt-4 text-xs leading-5 text-ink-500" data-no-print>
                                        Merasa sudah membayar reservasi ini?
                                        <button type="submit" form="reconcileForm"
                                            class="font-bold text-blueprint-700 underline underline-offset-2 transition-colors hover:text-blueprint-500"
                                            data-loading-label="Memeriksa...">
                                            <span>Periksa status pembayaran</span>
                                        </button>
                                    </p>
                                @endif
                            </div>
                        </div>
                    @elseif($booking->status === BookingStatus::Cancelled)
                        <div class="flex items-start gap-4 border border-dimension-500/40 bg-dimension-50 p-5">
                            <svg class="mt-0.5 h-6 w-6 shrink-0 text-dimension-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                            </svg>
                            <div>
                                <h2 class="font-annotation text-sm font-bold uppercase tracking-[0.06em] text-dimension-700">Reservasi dibatalkan
                                </h2>
                                <p class="mt-2 text-sm leading-6 text-ink-700">
                                    Reservasi ini tidak lagi aktif dan tanggalnya sudah dilepas kembali ke kalender
                                    unit. Anda dapat memesan ulang unit yang sama selama tanggalnya masih kosong.
                                </p>
                                <a href="{{ route('apartments.show', $booking->apartment->slug) }}"
                                    class="btn-primary mt-4">Pesan ulang unit</a>

                                @if ($canReconcile)
                                    {{-- Pembayaran yang ditolak boleh dicoba ulang
                                         pada order_id yang sama, jadi booking yang
                                         terlanjur dibatalkan bisa punya pembayaran
                                         berhasil yang notifikasinya belum sampai. --}}
                                    <p class="mt-5 border-t border-ink-200 pt-4 text-xs leading-5 text-ink-500" data-no-print>
                                        Merasa sudah membayar reservasi ini?
                                        <button type="submit" form="reconcileForm"
                                            class="font-bold text-blueprint-700 underline underline-offset-2 transition-colors hover:text-blueprint-500"
                                            data-loading-label="Memeriksa...">
                                            <span>Periksa status pembayaran</span>
                                        </button>
                                    </p>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="flex items-start gap-4 border border-ink-300 bg-paper-100 p-5">
                            <svg class="mt-0.5 h-6 w-6 shrink-0 text-ink-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <div>
                                <h2 class="font-annotation text-sm font-bold uppercase tracking-[0.06em] text-ink-700">Menginap selesai</h2>
                                <p class="mt-2 text-sm leading-6 text-ink-600">
                                    Terima kasih telah menginap bersama {{ $support['business_name'] }}.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Jalur bantuan tampil di semua status: masalah pembayaran,
                     pembatalan, dan pertanyaan saat menginap sama-sama butuh
                     kontak, dan belum ada email konfirmasi yang memuatnya. --}}
                <div class="border-t border-ink-300 bg-paper-200 p-6 sm:p-8">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="annotation annotation--strong uppercase">Butuh bantuan?</h2>
                            <p class="mt-2 text-sm leading-6 text-ink-600">
                                Hubungi kami dan sertakan kode reservasi
                                <strong class="font-annotation font-semibold text-ink-900">{{ $booking->booking_code }}</strong>.
                                {{ $support['hours'] }}.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 shrink-0 text-sm">
                            <a href="mailto:{{ $support['email'] }}?subject={{ rawurlencode('Bantuan reservasi ' . $booking->booking_code) }}"
                                class="inline-flex min-h-11 items-center gap-2 break-all font-semibold text-blueprint-700 transition-colors hover:text-blueprint-500">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Zm0 .5 9 6 9-6" />
                                </svg>
                                {{ $support['email'] }}
                            </a>
                            <a href="tel:{{ $support['phone_tel'] }}"
                                class="inline-flex min-h-11 items-center gap-2 font-semibold text-blueprint-700 transition-colors hover:text-blueprint-500">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M4 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L14 13l5 2v4a1 1 0 0 1-1.1 1A15 15 0 0 1 3 5.1 1 1 0 0 1 4 4Z" />
                                </svg>
                                {{ $support['phone'] }}
                            </a>
                        </div>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-ink-500" data-no-print>
                        Baca <a href="{{ route('legal.terms') }}" class="text-ink-700 underline underline-offset-2 transition-colors hover:text-blueprint-700">Syarat &amp; Ketentuan</a>
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
