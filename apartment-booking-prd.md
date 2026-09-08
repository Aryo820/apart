# PRD — ApartStay: Aplikasi Booking Apartemen

> Dokumen ini ditulisi ulang dengan bahasa sehari-hari. Substansinya sama: menggambarkan kondisi aplikasi sekarang berdasarkan kode yang benar-benar ada.

## 1. Ini Aplikasi Apa?

**ApartStay** itu website buat nyewa apartemen. Alurnya gini:

1. Orang buka website, lihat-lihat unit apartemen yang available.
2. Pilih unit, pilih tanggal menginap, sistem cek tanggalnya masih kosong atau nggak.
3. Booking, bayar online pakai Midtrans (kayak GoPay/BCA VA/kartu, semua via Midtrans).
4. Begitu bayarannya masuk, booking otomatis jadi **terkonfirmasi**. Nggak perlu chat admin, nggak perlu nunggu orang membalas.

Ada juga panel admin buat pemilik/ngelola: ngatur daftar apartemen, fasilitas, booking yang masuk, dan pembayaran.

**Ukuran suksesnya satu:** booking sukses tanpa tabrakan tanggal dan tanpa campur tangan manusia.

**Ciri khasnya:** ini bukan marketplace kayak Travelio/Airbnb. Yang nyewa berurusan langsung sama pemilik unit. Harga jelas per malam, nggak ada biaya kejutan di belakang.

## 2. Buat Siapa?

| Siapa | Ceritanya | Yang dia butuhin |
|---|---|---|
| Penyewa & ekspatriat | Profesional yang butuh tempat tinggal berperabot, mingguan sampai bulanan | Tanggal akurat, bisa booking sendiri, bukti reservasi |
| Wisatawan staycation | Liburan/weekend, nginap kayak di apartment-hotel | Cari unit cepat, harga jelas, konfirmasi instan |
| Admin/pemilik | Yang ngelola unit dan operasional | Gampang ngatur unit, booking, dan pembayaran |

Semua tampilan pakai bahasa Indonesia, dan mayoritas pengguna buka dari HP.

## 3. Yang Bisa Dilakukan di Aplikasi

### Buat Pengunjung (belum login)
- **Beranda**: halaman utama + unit unggulan.
- **Katalog unit**: bisa difilter — cari nama/alamat, pilih kota, harga minimum-maksimum, jumlah kamar, muat berapa orang. Bisa disortir (harga termurah/termahal, terbaru).
- **Detail unit**: foto, spesifikasi (kamar tidur, kamar mandi, luas, kapasitas orang), fasilitas, dan form booking.
- **Cek tanggal**: klik cek ketersediaan, sistem langsung jawab tanggal itu kosong atau udah dibooking orang.
- Halaman syarat & ketentuan + kebijakan privasi.

### Buat User yang Sudah Login
- Daftar/login akun sendiri.
- **My Bookings**: lihat semua riwayat reservasi.
- **Detail booking**: status booking, batas waktu bayar, rincian harga, dan bisa **print/cetak bukti reservasi**.
- Bisa **batalkan sendiri** — tapi cuma kalau belum bayar.

### Cara Kerja Booking & Pembayaran
- Tiap booking dikasih kode unik, misal `APT-20260806-K4X2B9QZ`.
- Harga dihitung: **harga per malam × jumlah malam**. Angka ini "dikunci" (disimpan permanen di booking), jadi walau harga unit berubah kemudian, tagihan tamu nggak berubah.
- Tanggal yang bentrok ditolak. Bahkan kalau dua orang booking di detik yang sama, sistem memastikan cuma satu yang menang — ini bagian paling dijaga di kode.
- Menginap yang **nyambung diperbolehkan**: tamu A check-out tanggal 10, tamu B boleh check-in tanggal 10.
- Setelah booking dibuat, muncul popup Midtrans buat bayar.
- Sistem dan Midtrans "ngobrol" lewat webhook (Midtrans ngasih tahu: ini bayarannya berhasil/gagal). Sistem cek tanda tangan keamanan + jumlah uangnya cocok, baru update status.
- Kalau webhook telat datang, pas tamu balik ke halaman booking sistem tetap tanya langsung ke Midtrans "jadi, bayarnya berhasil nggak?" — biar statusnya nggak nanggung.
- **Batas waktu bayar: 24 jam.** Lewat itu, booking pending-nya hangus (status `expired`) dan tanggalnya langsung bebas lagi buat orang lain — nggak nunggu scheduler.
- Satu akun maksimal pegang **3 booking pending** sekaligus — biar nggak ada yang iseng megang semua tanggal cuma buat ngerusak.
- Booking yang udah dibayar tapi ternyata tanggalnya udah keduluan diambil orang (kasus webhook telat banget): uangnya dicatat, kasusnya dicatat di log, refund-nya manual. Ini kasus yang jarang banget.

### Buat Admin (panel di `/admin`)
- Login khusus admin, user biasa nggak bisa masuk.
- Dashboard: total uang masuk (yang udah settlement), booking aktif, unit available, jumlah pelanggan.
- Kelola: Apartemen (foto, harga, status, unit unggulan), Booking, Payment, User, Fasilitas.
- Data keuangan dijaga: user atau unit yang punya riwayat booking **nggak bisa dihapus** (biar catatan uang tetap utuh). Admin juga nggak bisa nggak sengaja ngehancurin diri sendiri (hapus akun sendiri / turunin role sendiri itu diblokir).
- Admin bisa bikin booking manual buat tamu walk-in.

### Yang Sengaja Belum Ada
- Refund otomatis (masih manual lewat kontak).
- Tombol "bayar simulasi" — nggak ada, semua pembayaran beneran lewat Midtrans.
- Kupon, harga musiman, pajak.
- Review/testimoni, chat antar-user, bahasa lain selain Indonesia.
- Verifikasi email & lupa password (lihat bagian 7).

## 4. Alur Booking dari Awal Sampai Akhir

1. Tamu buka detail unit → pilih tanggal → cek availability → harga muncul langsung di layar.
2. Klik booking (wajib login dulu) → sistem cek tanggal → buat booking → muncul popup Midtrans.
3. Tamu bayar → Midtrans kabarin sistem → booking jadi **Confirmed**.
4. Tamu balik ke halaman booking → status udah update → bisa cetak bukti reservasi.

### Status Booking (dan apa artinya)

| Status | Artinya |
|---|---|
| **Pending** | Udah dibooking, nunggu dibayar (maks 24 jam) |
| **Confirmed** | Udah dibayar, tanggal dikunci |
| **Cancelled** | Tamu batal sendiri / pembayaran ditolak |
| **Expired** | Nggak dibayar sampai deadline |
| **Completed** | Tamu udah selesai menginap (status akhir) |

Status payment ngikutin istilah Midtrans: `pending`, `settlement` (berhasil), `expire`, `cancel`, `failed`. Pembayaran yang gagal boleh dicoba lagi, dan kalau berhasil kemudian tetap diakui.

## 5. Data yang Disimpan

| Data | Isinya |
|---|---|
| User | email, password (terenkripsi kuat), role (admin/user biasa) |
| Apartment | nama, slug (alamat URL), harga/malam, kamar, luas, kapasitas, foto, status |
| Facility | nama + ikon fasilitas (WiFi, kolam renang, dll) |
| Booking | kode unik, tanggal check-in/out, jumlah malam, total harga, status, catatan |
| Payment | order ID dari Midtrans, jumlah uang, status, token pembayaran |

## 6. Hal Non-Fitur yang Dijaga

**Keamanan**
- Semua input user dicuci (nggak bisa inject SQL/XSS).
- Nggak bisa spam: login max 10x/menit, daftar 5x/menit, booking 10x/menit, cek tanggal 30x/menit per IP/user.
- Webhook dari Midtrans wajib bawa tanda tangan sah — yang palsu ditolak, tanpa kecuali.
- Upload foto cuma boleh PNG/JPEG/WebP maksimal 2 MB (file SVG ditolak karena bisa dipakai ngerusak situs).
- Password di-hash, session diperbarui pas login/logout, dan ada security headers standar.

**Keandalan**
- Double-booking mustahil terjadi, bahkan saat dua request datang bareng.
- Kalau scheduler tukang menutup booking pending telat jalan, tanggal tetap nggak macet.
- Webhook datang dua kali / telat / acak urutannya — status tetap benar.

**Tampilan & Performa**
- CSS di-compile (bukan dimuat dari internet), JavaScript polos tanpa framework berat — buat HP kelas menengah di jaringan Indonesia.
- Gambar rusak otomatis diganti placeholder.
- Aksesibilitas: tombol gampang ditekan di HP, support screen reader, animasi bisa dimatikan (bagi yang butuh).

**Kualitas Kode**
- Ada test otomatis (~25 test) yang khusus menguji: tabrakan tanggal, request bersamaan, pembayaran palsu, spam, dan hak akses.
- Tiap push ke GitHub, semua test dijalankan otomatis (CI). Kode yang berantakan ditolak.

## 7. Yang Belum Diputuskan (catatan untuk ke depan)

1. **Nama brand & desain visual** — bebas diganti, nggak ada yang mengikat.
2. **Verifikasi email & reset password** — belum ada. Kalau user lupa password, sekarang belum ada jalurnya.
3. **Content Security Policy ketat** — belum aktif karena popup Midtrans butuh pengecualian.
4. **Batas waktu bayar 24 jam** — itu nilai yang udah ada, bukan keputusan final. Bisa diubah di config (`BOOKING_PAYMENT_EXPIRY_MINUTES`).
5. **Aturan refund** — belum ada. Sementara ini, booking yang udah dibayar nggak bisa dibatalkan mandiri; harus lewat kontak bantuan.

## 8. Ukuran Sukses

| Yang diukur | Target |
|---|---|
| Kasus double-booking | 0 (ada test khusus buat ini) |
| Booking jadi Confirmed tanpa admin campur tangan | ≥95% |
| Booking pending hangus tepat waktu | 100% (toleransi ±5 menit) |
| Webhook palsu ditolak | 100% |

## 9. Kalau Mau Deploy ke Produksi, Jangan Lupa

- Mode production dinyalakan, debug dimatikan, pakai HTTPS.
- Config Midtrans diganti ke key produksi.
- Cron scheduler jalan tiap menit (buat hanguskan booking pending).
- Kredensial admin ditentukan sendiri (bukan default).
- Webhook Midtrans harus bisa diakses dari internet.
