# Observability — ApartStay

Status: **REPOSITORY READY — external production configuration still required.**

Dokumen ini memetakan sinyal operasional yang dihasilkan kode di repo, apa
yang sudah dibangun vs apa yang wajib dikonfigurasi saat deployment, dan
tindakan operator untuk masing-masing.

## Inventory sinyal

| Sinyal | Sumber | Severity | Visibilitas operator (sebelum aksi deployment) |
|---|---|---|---|
| Insiden finansial `payment_settled_booking_unavailable` | `PaymentStatusApplier` → `Log::warning` + kolom/filter "Perlu Tindakan" di `/admin/payments` + stat "Perlu Tindakan" di dashboard | HIGH | Log file + panel Filament |
| Kegagalan scheduled task (`scheduler_task_failed`) | Listener `ScheduledTaskFailed` di `AppServiceProvider` → `Log::error` | HIGH | Log file |
| Webhook signature invalid / input non-scalar | `PaymentController` (403) | MEDIUM | Log + access log |
| Exception tak tertangani | Laravel handler default → `storage/logs/laravel.log` | HIGH | Log file |
| Health aplikasi | `GET /up` (Laravel built-in) | — | HTTP 200/500 |
| Command `bookings:expire-pending` run summary | `$this->info("Booking expired: N")` | INFO | Output schedule:run |

Konteks structured log insiden finansial (aman untuk dibaca siapa pun —
tanpa kredensial, tanpa session, tanpa payload mentah):

```text
incident_type, booking_id, booking_code, booking_status, payment_id,
order_id, apartment_id, amount, payment_status, check_in, check_out,
conflicting_bookings, transaction_id
```

## Yang diimplementasikan di repository

1. **Structured financial logging** — `payment_settled_booking_unavailable`
   dengan identifier lengkap (B2).
2. **Scheduler failure listener** — kegagalan task terjadwal menulis
   `scheduler_task_failed` (ERROR) ke log aplikasi; `withoutOverlapping()`
   dan idempotensi command tidak diubah (B3).
3. **Admin visibility insiden finansial** — stat dashboard + kolom + filter
   "Perlu Tindakan" (lihat `FinancialIncidentTest`).
4. **`/up` health endpoint** — Laravel built-in: murah (tanpa query DB per
   request), tidak membocor konfigurasi/secret, cocok untuk uptime monitor.

## Yang WAJIB dikonfigurasi saat deployment

Repository tidak mengasumsikan provider — berikut keputusan deployment:

1. **Log drain/shipper**: kirim `storage/logs/laravel.log` (atau stdout)
   ke agregator pilihan tim (CloudWatch/DO Logs/Grafana Loki/ELK/apa pun).
   Tanpa ini, sinyal tinggal di file di satu server.
2. **Error tracker eksternal** (Sentry/Flare/Bugsnag atau setara): belum
   dipasang di repo — sengaja, karena pilihan provider adalah keputusan
   deployment, bukan repo. Cara paling cepat: pasang paket resmi provider
   terpilih, set DSN lewat env var (`SENTRY_LARAVEL_DSN` dll), daftarkan
   channel ke `LOG_STACK`. Jangan commit DSN.
3. **Uptime monitor** arahkan ke `GET /up` tiap 1–5 menit + alert downtime.
4. **Alert atas log ERROR**: trigger untuk `scheduler_task_failed` dan
   `payment_settled_booking_unavailable` (severity tinggi, volume ≈ 0 pada
   operasi normal — alert per-kejadian, bukan threshold).
5. **Cron** `schedule:run` per menit (sudah di README checklist) — tanpa ini
   expiry booking tidak berjalan dan `scheduler_task_failed` tidak pernah
   terlihat karena task tidak pernah jalan.

## What-good-looks-like saat insiden finansial terjadi

1. Alert masuk (log ERROR / error tracker) dengan `booking_code` +
   `payment_id`.
2. Buka `/admin/payments` → filter "Perlu Tindakan" → buka booking terkait.
3. Refund manual via dashboard Midtrans (bukan aplikasi — by design).
4. Insiden hilang dari daftar saat kombinasi status tidak lagi cocok
   (mis. admin mengubah status booking via panel guard).

Status insiden adalah **computed** (scope dari persisted state), bukan flag
manual — tidak ada resolution state yang bisa basi/ketinggalan. Menandai
"selesai" = menyelesaikan keadaannya (refund + status booking), yang otomatis
menghilangkan insiden dari daftar. Gateway status (Settlement) TIDAK diubah
oleh proses resolution — itu catatan Midtrans.
