<?php

return [
    /*
     * Batas waktu pembayaran, dihitung dari bookings.created_at.
     *
     * Nilainya 1440 menit (24 jam) karena itu angka yang SUDAH dipakai project
     * ini sebelum perubahan ini — scheduler lama membatalkan pending >24 jam,
     * dan BookingController::show memperbarui snap token yang lebih tua dari
     * 24 jam. Jadi ini mempertahankan perilaku existing, bukan angka baru yang
     * dikarang. Durasi final tetap keputusan produk (lihat Remaining Decisions).
     *
     * Dipakai di empat tempat yang harus sepakat:
     *   - Booking::scopeBlocking      (pending kedaluwarsa tidak mengunci tanggal)
     *   - Booking::paymentDeadlineAt  (deadline yang ditampilkan ke user)
     *   - MidtransService             (expiry yang dikirim ke gateway)
     *   - bookings:expire-pending     (command penutup status)
     */
    'payment_expiry_minutes' => (int) env('BOOKING_PAYMENT_EXPIRY_MINUTES', 1440),
];
