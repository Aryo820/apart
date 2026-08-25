<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Menutup booking pending yang melewati batas waktu pembayaran.
 *
 * Menggantikan closure lama yang menandainya 'cancelled' (tidak bisa dibedakan
 * dari pembatalan sungguhan) dan hardcode 24 jam. Sekarang lewat command
 * sehingga bisa dijalankan manual, diuji, dan durasinya dari config.
 *
 * Tiap 5 menit, bukan hourly: yang terlambat di sini hanya statusnya, bukan
 * ketersediaan tanggal — Booking::scopeBlocking sudah membebaskan tanggal tepat
 * saat deadline lewat. Tapi tamu yang membuka halaman reservasinya sendiri
 * sebaiknya tidak melihat "menunggu pembayaran" satu jam setelah waktunya habis.
 *
 * withoutOverlapping: kalau satu jalannya melambat karena data menumpuk, jalan
 * berikutnya menunggu daripada memperebutkan baris yang sama.
 *
 * Perlu `php artisan schedule:work` (dev) atau satu entri cron per menit yang
 * memanggil `schedule:run` (produksi) agar benar-benar berjalan.
 */
Schedule::command('bookings:expire-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Tandai booking pending yang melewati batas waktu pembayaran sebagai expired');
