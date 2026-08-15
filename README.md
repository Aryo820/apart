# ApartStay — Apartment Booking

Aplikasi reservasi apartemen berbasis **Laravel 13 + Filament 5**, dengan pembayaran via **Midtrans Snap**. Admin mengelola konten lewat panel Filament; pelanggan browsing, memesan, dan membayar lewat halaman publik.

## Stack

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.3, Laravel 13 |
| Admin panel | Filament 5 (path `/admin`) |
| Frontend | Blade + Tailwind CSS 4 + Vite |
| Database | SQLite (dev) / MySQL (production) |
| Payment | Midtrans Snap (sandbox/production) |
| Tests | PHPUnit 12 |

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # credentials dari .env (lihat bawah) atau random + di-print
npm install && npm run build
php artisan serve                   # http://localhost:8000
```

Kredensial seed dibaca dari env — tidak ada password default yang di-commit:

```bash
ADMIN_EMAIL=admin@apart.com
ADMIN_PASSWORD=your-secure-password   # kosong → password random dicetak saat seed
USER_EMAIL=user@apart.com
USER_PASSWORD=your-secure-password
```

## Environment Variables

```
MIDTRANS_SERVER_KEY=...       # dari dashboard Midtrans
MIDTRANS_CLIENT_KEY=...
MIDTRANS_IS_PRODUCTION=false  # true untuk production
APP_DEBUG=false               # wajib di production
ADMIN_EMAIL / ADMIN_PASSWORD  # kredensial admin seed (jangan kosong di production)
```

## Arsitektur & Alur

### Booking (double-booking safe)
1. `POST /booking` memvalidasi tanggal, menghitung `total_nights × price_per_night`.
2. Seluruh proses (cek konflik + insert booking + insert payment) berjalan dalam **satu transaksi DB** dengan `lockForUpdate` pada baris booking yang bertabrakan — dua request konkuren tidak bisa lulus cek bersamaan.
3. Snap token Midtrans dibuat di dalam transaksi; bila gateway gagal, transaksi rollback dan booking tidak pernah tersimpan.

### Alur pembayaran (Midtrans)
- Snap token dikirim ke browser; callback hasil bayar diterima Midtrans via `POST /payment/midtrans-notification` (exempt CSRF, dilindungi signature SHA-512).
- `order_id` = `{booking_code}-{timestamp}`; `booking_code` dipisah dengan `Str::beforeLast`.
- Status `capture` diperlakukan terpisah via `fraud_status` (accept/challenge/deny).
- Idempotensi: payment dengan status final (`settlement/failed/cancel/expire`) tidak diproses ulang; row payment di-lock selama pemrosesan agar webhook ganda tidak balapan.
- `gross_amount` webhook diverifikasi terhadap nilai payment.
- Pembayaran pelanggan hanya diproses melalui Midtrans Snap dan callback terverifikasi; tidak ada endpoint simulasi pembayaran.

### Status domain (enum tunggal)
`app/Enums/BookingStatus.php` dan `app/Enums/PaymentStatus.php` — satu sumber kebenaran status, dipakai di controller, model, view, dan panel Filament. Jangan pakai string mentah di kode baru.

### Pembersihan booking menggantung
`routes/console.php` menjadwalkan job tiap jam: booking `pending` berumur > 24 jam → `cancelled`. Jalankan scheduler di production:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Panel Admin

- `/admin` — login admin (role `admin`).
- Resources: Apartemen, Booking, Payment, User, Facility.
- Keamanan data: FK `restrictOnDelete` — user/apartemen dengan riwayat booking tidak bisa dihapus (data finansial terjaga). SQLite didukung via table rebuild.

## Testing

```bash
composer test             # unit + feature (PHPUnit) — setara php artisan test
vendor/bin/pint --test    # cek style (Laravel Pint)
```

Environment testing dipaksa di `tests/TestCase.php` (APP_ENV, sqlite :memory:, dsb) sebelum app boot — ini membuat suite berjalan konsisten di Windows dan CI tanpa bergantung pada `<env>` phpunit.xml.

Coverage utama: alur booking (konflik tanggal, adjacency, rollback gateway, otorisasi, halaman booking cancelled), callback Midtrans (signature, idempotensi, fraud_status, mismatch amount), rate limiting login/register.

## CI/CD

GitHub Actions di `.github/workflows/tests.yml`: composer install → migrate sqlite → `php artisan test` → `pint --test`.

## Keamanan (ringkasan)

- Semua query parameterized (ORM); output Blade ter-escape; `{!! !!}` hanya dipakai bersama `e()`.
- Password bcrypt (12 rounds); session di-regenerate saat login/logout.
- Rate limit: login `10/menit/IP`, register `5/menit/IP`, booking `10/menit`, cek availability `30/menit` — per-bucket terpisah.
- Panel admin: hanya role `admin` via `FilamentUser::canAccessPanel()` **plus** Policy admin-only per resource (defense-in-depth di level Gate).
- Security headers (nosniff, SAMEORIGIN, Referrer-Policy, Permissions-Policy, HSTS di production) via middleware global.
- Webhook Midtrans CSRF-exempt tapi wajib signature valid.
- Frontend publik memakai Tailwind CSS ter-compile (Vite build), bukan CDN runtime.

## Batasan yang disengaja

- CSP ketat belum diterapkan (butuh nonce support pada inline script snap.js) — lihat `app/Http/Middleware/SecurityHeaders.php`.
- Enums SQLite `enum` di migration adalah string biasa; MySQL memakai tipe `enum` asli.
- Belum ada verifikasi email / reset password.
