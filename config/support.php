<?php

$phone = env('SUPPORT_PHONE', '+62 21 5000 1234');

return [
    /*
     * Satu sumber untuk identitas bisnis dan jalur bantuan. Dipakai footer,
     * halaman legal, dan halaman reservasi — jadi ganti nomor/email cukup di
     * .env, bukan mencari-cari string di dalam Blade.
     */
    'business_name' => env('BUSINESS_NAME', 'Santhosa'),
    'email' => env('SUPPORT_EMAIL', 'support@santhosa.test'),
    'phone' => $phone,

    /*
     * Bentuk aman untuk href="tel:" — dihitung sekali di sini supaya setiap
     * tempat yang menampilkan kontak tidak mengulang preg_replace sendiri.
     */
    'phone_tel' => preg_replace('/[^0-9+]/', '', $phone),

    'hours' => env('SUPPORT_HOURS', 'Setiap hari, 08.00–21.00 WIB'),

    /*
     * Tanggal revisi dokumen legal. Bukan env: ini bagian dari isi dokumen,
     * dan harus ikut berubah lewat commit saat teksnya diperbarui.
     */
    'legal_updated_at' => '15 Agustus 2026',
];
