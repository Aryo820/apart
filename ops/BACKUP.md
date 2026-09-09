# Runbook Backup & Restore — ApartStay

Operasional database produksi ApartStay (MySQL/InnoDB). Dokumen ini adalah
bagian dari repository; eksekusinya (cron, storage offsite, drill) adalah
tanggung jawab deployment.

## Target pemulihan

| Target | Nilai | Catatan |
|---|---|---|
| RPO | ≤ 24 jam | Mekanismenya dump harian penuh — jangan mengklaim lebih kecil tanpa mekanisme yang lebih kecil (lihat "RPO lebih rendah"). |
| RTO | ≤ 4 jam | Asumsi: dump tersedia offsite, server DB baru siap, restore + verifikasi drill sudah pernah diuji. |

Konsistensi penting: audit lama menulis "RPO 15–60 menit" sambil menyarankan
`mysqldump` harian — keduanya tidak cocok. Baseline ini jujur: **daily dump =
worst-case RPO 24 jam**.

## Apa yang di-backup

Full logical dump database transaksi via `mysqldump --single-transaction`
(InnoDB snapshot konsisten, tanpa lock):

- Seluruh tabel: `users`, `apartments`, `facilities`, `apartment_facility`,
  `bookings`, `payments`, `sessions`, `cache`, `jobs`, `migrations`,
  `password_reset_tokens`, dan tabel lain apa pun yang ada.
- Routines, triggers, events, BLOB (hex-blob).

Full database dump dipakai **bukan** per-tabel: restore harus menghasilkan
aplikasi yang konsisten, dan memilih tabel satu-s satu adalah sumber bug
restore.

File upload unit (`storage/app/public/...`) TIDAK termasuk dump DB — jalankan
juga backup file untuk `storage/app` (lihat bawah).

## Frekuensi, retensi, rotasi

- Frekuensi: 1×/hari (pilih jam rendah, mis. 02:30 waktu server).
- Rotasi lokal: `KEEP_LAST=14` (14 file terakhir) di server.
- Retensi offsite: minimal **30 hari** harian. Provider-neutral — pilihan
  konkret (object storage / backup host terpisah / managed backup) adalah
  keputusan deployment, bukan repository.

## Enkripsi & offsite

Backup lokal di server yang sama **BUKAN disaster recovery** — kebakaran/
ransomware/disk failure menghapus aplikasi beserta backupdate. Wajib:

1. Kirim file dump ke destination **berbeda** dari server aplikasi/DB:
   object storage (S3/R2/Spaces/apa pun yang dipilih tim), managed backup,
   atau backup host terpisah.
2. Enkripsi in-transit (TLS) dan at-rest di tujuan. Untuk enkripsi file
   mandiri: `gpg -c apartstay-db-*.sql.gz` sebelum kirim bila provider tidak
   mengenkripsi. Key GPG disimpan terpisah dari backup (password manager
   tim), bukan di server yang sama.
3. Akses tujuan: hanya tim ops/owner. Tidak ada bucket publik.

## Ownership

- Pemilik runbook: tim operasional ApartStay (satu nama konkret per deployment).
- Trigger review runbook: setiap perubahan skema besar atau perubahan provider.

## Failure handling

- `ops/backup-db.sh` keluar **non-zero** dan mencetak ERROR ke stderr bila
  gagal — arahkan output cron ke file/log collector.
- Cron wajib alert bila backup gagal 2× berturut-turut (uptime monitor /
  log alert — lihat `ops/OBSERVABILITY.md`).
- Jangan pernah menghapus backup lama sebelum backup baru terverifikasi ada
  di offsite.

## Verifikasi rutin

- Setiap restore drill (minimal 1×/kuartal, atau setelah perubahan skema):
  `backup → restore ke DB disposable → ops/verify-restore.sh`.
- Jika tidak punya environment disposable: minimal `gunzip -t file.sql.gz`
  tiap minggu (integritas kompresi) + cek ukuran tidak turun drastis.

## Restore procedure

```bash
# 1. Siapkan database disposable/target (BUKAN production, kecuali insiden nyata)
mysql -u root -e "CREATE DATABASE apart_restore_drill;"

# 2. Restore dari file backup
RESTORE_DATABASE=apart_restore_drill ./ops/restore-db.sh storage/backups/apartstay-db-<ts>.sql.gz

# 3. Verifikasi konsistensi terhadap DB sumber
RESTORE_DATABASE=apart_restore_drill SOURCE_DB=<db_asli> ./ops/verify-restore.sh

# 4. Baru pertimbangkan promote (insiden nyata): restore ke DB production
#    dilakukan hanya via FORCE_PRODUCTION=1 + review ops.
```

Guard yang dimiliki `ops/restore-db.sh`:

- Target DB wajib eksplisit (`RESTORE_DATABASE`) — tidak pernah membaca
  `DB_DATABASE` .env untuk menimpa.
- `APP_ENV=production` ditolak tanpa `FORCE_PRODUCTION=1`.
- Tidak ada prompt interaktif — aman untuk drill otomatis.

Setelah restore production: `php artisan migrate:status` (tidak boleh ada
migrasi pending), `php artisan config:cache`, cek `/up`, lalu komunikasikan
status ke tamu yang bookingnya terdampak.

## RPO lebih rendah (opsional, managed DB)

Bila deployment memakai managed MySQL dengan binlog/PITR: aktifkan binlog
retention ≥ 48 jam + automated snapshot tiap jam → RPO ~1 jam (jam
snapshot terakhir). Dokumentasikan di deployment (bukan repo) karena
sepenuhnya bergantung provider. Tanpa itu, klaim RPO tetap 24 jam.

## Backup file storage

Upload foto unit hidup di `storage/app/public/apartments`. Minimal:

```bash
tar czf storage/backups/apartstay-storage-$(date -u +%Y%m%d).tar.gz storage/app
```

Jadikan bagian dari jadwal harian yang sama dengan DB, kirim offsite bersama
dump DB. (Foto unit hilang = halaman detail rusak, walau DB utuh.)
