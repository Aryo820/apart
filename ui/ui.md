UI/UX AUDIT — Santhosa Apartment Booking

Overall Score

┌────────────────────┬────────┬───────────────────────────────────────────────────────────────────┐
│ Aspek │ Skor │ Alasan singkat │
├────────────────────┼────────┼───────────────────────────────────────────────────────────────────┤
│ Visual Design │ 5/10 │ Home page kuat & punya karakter; 6 halaman lain masih desain lama │
├────────────────────┼────────┼───────────────────────────────────────────────────────────────────┤
│ UX │ 5/10 │ Flow jalan, tapi availability & review step tidak ada │
├────────────────────┼────────┼───────────────────────────────────────────────────────────────────┤
│ Usability │ 5/10 │ Error handling tidak inline, input hilang saat gagal │
├────────────────────┼────────┼───────────────────────────────────────────────────────────────────┤
│ Responsive │ 5/10 │ Breakpoint ada, tapi ada overflow & auto-zoom iOS │
├────────────────────┼────────┼───────────────────────────────────────────────────────────────────┤
│ Accessibility │ 4/10 │ CTA utama gagal kontras WCAG AA (terukur, bukan dugaan) │
├────────────────────┼────────┼───────────────────────────────────────────────────────────────────┤
│ Consistency │ 3/10 │ Dua design system hidup bersamaan dalam satu produk │
├────────────────────┼────────┼───────────────────────────────────────────────────────────────────┤
│ Trust & Conversion │ 4/10 │ Copy internal bocor ke publik, trust signal minim │
├────────────────────┼────────┼───────────────────────────────────────────────────────────────────┤
│ Overall │ 4.5/10 │ │
└────────────────────┴────────┴───────────────────────────────────────────────────────────────────┘

---

Executive Summary

Apakah UI/UX ini sudah bagus untuk production? Belum.

Alasan utamanya bukan soal selera, tapi fakta struktural: redesign hanya selesai di satu halaman.

Yang sudah di-redesign (palet ink/gold, Playfair Display, brand "Santhosa"):
layouts/app.blade.php, home.blade.php, components/site-navbar.blade.php, site-footer.blade.php, apartment-card.blade.php, facility-icon.blade.php

Yang masih desain lama (palet slate, rounded-2xl, brand "ApartStay"):
apartments/index.blade.php, apartments/show.blade.php, bookings/index.blade.php, bookings/show.blade.php, auth/login.blade.php, auth/register.blade.php (+
welcome.blade.php yang orphan)

Bukti terukur — jumlah token warna per file:

home.blade.php ink:9 gold:7 slate:1
apartment-card.blade.php ink:6 gold:4 slate:0
site-navbar.blade.php ink:15 gold:9 slate:0
─────────────────────────────────────────────────────
apartments/index.blade.php ink:1 gold:0 slate:33 brand:11
apartments/show.blade.php ink:2 gold:0 slate:36 brand:19
auth/register.blade.php ink:0 gold:0 slate:25 brand:13
bookings/index.blade.php ink:1 gold:0 slate:14 brand:6

Karena navbar & footer baru dipakai di semua halaman, setiap halaman lama sekarang punya jahitan yang terlihat: header ink-900 + gold + serif di atas, lalu
konten slate-800 + rounded-xl + sans di bawah. Tepat setelah user menekan "Cari" di hero — momen paling penting di funnel — kualitas visual jatuh.

Lebih buruk: --color-brand-\* di resources/css/app.css:37-47 sudah di-remap ke gold. Halaman lama jelas dirancang untuk brand teal/biru — buktinya
auth/login.blade.php:9: bg-gradient-to-tr from-brand-500 to-teal-400, sekarang jadi gradient emas → teal. Jadi halaman lama tidak hanya beda dari yang baru,
tapi juga tidak lagi konsisten dengan dirinya sendiri.

Ini kondisi paling merugikan: lebih buruk daripada "semua lama" atau "semua baru". User menafsirkan inkonsistensi visual sebagai situs belum jadi — di produk
yang meminta pembayaran, itu langsung menekan konversi.

---

Strengths

Ini yang nyata bagus dan harus dipertahankan, dengan buktinya:

1. Fondasi CSS-nya benar-benar rapi (resources/css/app.css)
   Design token di @theme, .gold-button/.search-field/.empty-state sebagai komponen, prefers-reduced-motion dihormati (baris 258-271), :focus-visible global 2px
   gold offset 3px (baris 87-90). Ini di atas rata-rata proyek Laravel.

2. Aksesibilitas struktural pada bagian yang baru
   skip-link fungsional (layouts/app.blade.php:23 + CSS 112-128), aria-live="polite" di container flash (baris 27), aria-expanded/aria-controls pada toggle
   mobile (site-navbar.blade.php:41-43), Escape menutup menu dan mengembalikan fokus ke toggle (app.js:20-25), semua nav item min-h-11/min-h-12 = ≥44px touch
   target. Ini disengaja, bukan kebetulan.

3. Kontras .gold-button sangat baik
   gold-400 #e4b82f dengan teks ink-950 #07101f = 10.2:1. Bandingkan dengan CTA lama (poin Critical #3 di bawah) — ini bukti komponen baru dibuat lebih
   hati-hati.

4. Kartu apartemen baru lebih baik secara informasional
   apartment-card.blade.php punya flex-wrap pada meta row (baris 46), width/height eksplisit untuk mencegah CLS, loading/fetchpriority kondisional via prop
   $priority. Kartu lama (apartments/index:82) tidak punya flex-wrap.

5. Empty state dipikirkan
   Home menangani $featuredApartments->isEmpty() dan $facilities->isEmpty() dengan CTA lanjutan, bukan halaman kosong.

6. Resolusi path gambar yang defensif — sayangnya hanya di 2 tempat
   apartment-card.blade.php:7-15 dan home.blade.php:10-20 menangani URL absolut, path /-prefixed, dan path disk. Ini pola yang benar. Masalahnya justru karena
   ini tidak dipakai di tempat lain (lihat Critical #2).

---

Critical Issues

🔴 C1 — Copy internal developer tampil ke pengunjung publik

Location: resources/views/home.blade.php:191, dan :158

"Area ini sudah disiapkan mengikuti komposisi Figma dan akan
menampilkan testimoni setelah sumber datanya tersedia."

"Daftar fasilitas akan tampil setelah ditambahkan melalui panel admin."

Problem: Halaman depan memberi tahu calon penyewa tentang Figma dan panel admin.

Why it matters: Section "Apa Kata Mereka" adalah slot social proof — elemen trust tertinggi di halaman. Yang tampil sekarang justru pengakuan eksplisit bahwa
situs belum selesai, di tempat yang seharusnya paling meyakinkan. Tidak ada tabel review di database, jadi placeholder ini permanen, bukan sementara.

Recommended solution: Hapus section testimonial sampai ada data reviews, atau ganti dengan trust signal yang datanya sudah ada (jumlah unit aktif, jumlah
kota, "pembayaran diproses Midtrans"). Copy empty state harus bicara ke penyewa, bukan ke admin.

---

🔴 C2 — Gambar apartemen akan rusak untuk semua unit yang di-upload admin

Location: apartments/index.blade.php:64, apartments/show.blade.php:38 dan :44, bookings/index.blade.php:30, bookings/show.blade.php:48

Problem: Kelima tempat itu memakai src="{{ $apt->main_image }}" mentah. Tidak ada accessor main_image di app/Models/Apartment.php (sudah saya cek), jadi yang
keluar adalah nilai DB apa adanya.

app/Filament/Resources/ApartmentResource.php:115-118 menyimpan lewat FileUpload->directory('apartments/main') → nilai DB = apartments/main/xxx.jpg (path
relatif).

Hasil di browser: <img src="apartments/main/xxx.jpg"> di halaman /apartments/some-slug di-resolve jadi /apartments/apartments/main/xxx.jpg → 404.

Alasan ini belum terlihat: database/seeders/DatabaseSeeder.php:80,100,... memakai URL Unsplash absolut. Jadi bug ini tidak muncul di data seeder, hanya di
data produksi nyata.

Why it matters: Katalog dan halaman detail tanpa foto = tidak ada yang mau booking. Ini bukan masalah estetika, ini kegagalan fungsional yang muncul tepat
saat admin mulai memakai sistemnya. Halaman home tetap normal karena apartment-card.blade.php:7-15 me-resolve path dengan benar — jadi gejalanya
membingungkan: home bagus, listing rusak.

Recommended solution: Pola yang benar sudah ada di codebase. Angkat logika apartment-card.blade.php:7-15 menjadi accessor di model (atau satu komponen
<x-apartment-image>), lalu pakai di kelima tempat. Sekalian tambahkan fallback placeholder — main_image NOT NULL di migration, tapi item images (JSON,
nullable) di apartments/show.blade.php:44 tidak dijamin ada.

---

🔴 C3 — CTA utama gagal kontras WCAG AA (terukur)

Location: auth/login.blade.php:35, auth/register.blade.php:43, apartments/index.blade.php:46, apartments/show.blade.php:158, bookings/index.blade.php:19 dan
:69

Problem: Teks putih di atas brand-500 (#c99a18) dan brand-600 (#a8760e):

┌────────────────────────────────────┬────────┬────────────┐
│ Kombinasi │ Rasio │ AA (4.5:1) │
├────────────────────────────────────┼────────┼────────────┤
│ white on brand-500 #c99a18 │ 2.6:1 │ gagal │
├────────────────────────────────────┼────────┼────────────┤
│ white on brand-600 #a8760e │ 3.9:1 │ gagal │
├────────────────────────────────────┼────────┼────────────┤
│ ink-950 on gold-400 (.gold-button) │ 10.2:1 │ lulus │
└────────────────────────────────────┴────────┴────────────┘

Semua tombol itu text-sm (14px) — tidak memenuhi pengecualian "large text" (18.66px bold).

Why it matters: Yang terdampak persis tombol paling penting: "Masuk", "Daftar Sekarang", "Booking Sekarang", "Bayar Sekarang". Di luar isu kepatuhan, teks
emas-di-emas terlihat pudar dan murah — efeknya berlawanan dengan kesan premium yang dituju. Ironisnya .gold-button yang baru sudah memecahkan ini (teks gelap
di atas emas); halaman lama belum ikut.

Recommended solution: Pakai .gold-button yang sudah ada. Kalau ingin CTA gradient tetap, teksnya harus gelap (text-ink-950), bukan putih.

---

🔴 C4 — Tidak ada error inline sama sekali; input hilang saat gagal

Location: Semua view. Saya cek: 0 direktif @error di seluruh resources/views.

Problem: Semua error validasi keluar sebagai satu toast di layouts/app.blade.php:37-53 — fixed top-20, tanpa tombol tutup, tanpa auto-dismiss, tanpa anchor ke
field.

Di halaman detail, form booking ada di sidebar kanan (apartments/show.blade.php:118, sticky top-28). Kalau BookingController.php:83 mengembalikan "Apartemen
ini tidak tersedia pada tanggal yang Anda pilih", pesan itu muncul di atas viewport — bisa jauh dari form, atau tidak terlihat kalau user sedang scroll.

Lebih parah: controller memanggil ->withInput() (baris 83, 85), tapi form-nya tidak memakai old() sama sekali (apartments/show.blade.php:128, 133, 138). Jadi
input tanggal dan catatan tetap hilang meski server sudah mengirimkannya kembali. auth/\* sudah pakai old() dengan benar — inkonsistensi di dalam codebase
sendiri.

Why it matters: Skenario paling umum di situs booking adalah tanggal bentrok. Sekarang penanganannya: toast di tempat yang salah + form yang direset. User
harus mengisi ulang tanpa tahu tanggal mana yang bermasalah. Ini titik drop-off terbesar di funnel.

Recommended solution: @error('check_in') inline di bawah masing-masing field, value="{{ old('check_in') }}", dan tombol tutup pada toast global.

---

🔴 C5 — Redesign setengah jalan (akar dari C1–C4)

Location: 6 view yang dilist di Executive Summary

Problem/Why it matters: Sudah dibahas di atas. Yang perlu ditegaskan: mockup untuk halaman-halaman ini sudah ada — resources/views/design/daftarunit.png dan
detail.png menunjukkan sidebar filter, price range slider, kategori checkbox, chip fasilitas, pagination gelap kustom, galeri mosaik, panel booking sticky.
Tidak satu pun ada di implementasi. Jadi ini bukan keputusan desain, ini pekerjaan yang belum selesai.

Recommended solution: Dua pilihan yang sah, dan keduanya lebih baik dari kondisi sekarang:

1. Selesaikan 6 halaman itu (rekomendasi saya — mockup sudah tersedia, komponen .gold-button/.search-field/.empty-state/x-apartment-card sudah siap dipakai).
2. Kembalikan navbar/footer ke desain lama dan tunda redesign. Konsisten-tapi-biasa mengalahkan setengah-premium.

⚠️ Catatan penting untuk opsi 1: mockup memuat data yang tidak ada di database. Lihat bagian Data/UI Issues — jangan implementasikan mockup secara literal.

---

UX Issues

🟠 U1 — Availability tidak pernah ditampilkan, padahal datanya sudah dihitung

ApartmentController.php:91-99 menghitung $bookedDates dan mengirimkannya ke view. apartments/show.blade.php tidak pernah memakainya (saya grep: nol
referensi). Endpoint POST /apartments/{id}/availability (routes/web.php:16, controller baris 104-128) juga tidak dipanggil dari mana pun — tidak ada fetch di
app.js maupun di script inline halaman detail.

Konsekuensinya: user memilih tanggal, klik Booking, baru ditolak. Padahal backend sudah bisa memberi tahu sebelumnya. Ini pekerjaan yang sudah dibayar tapi
tidak dipakai.

🟠 U2 — Tidak ada langkah review sebelum booking dibuat

apartments/show.blade.php:158 submit langsung ke bookings.store. Aksi itu membuat record pending, mengunci tanggal di kalender (Booking::conflicting
menyertakan status Pending), dan memanggil Midtrans. Tidak ada halaman konfirmasi, tidak ada dialog, tidak ada data-submit-loading (padahal helper-nya ada di
app.js:28-46 dan dipakai di form hero home).

design/bookingform.png memperlihatkan halaman "Konfirmasi Pemesanan" lengkap dengan step indicator 1. Detail → 2. Pembayaran → 3. Selesai. Implementasi
melewatinya sepenuhnya. Di flow yang sekarang user tidak pernah tahu ia berada di langkah berapa.

🟠 U3 — Total biaya bisa tidak pernah terlihat sebelum submit

Kartu perhitungan harga (apartments/show.blade.php:142) hidden sampai kedua tanggal terisi. Tombol "Booking Sekarang" aktif dan bisa diklik sebelum itu. Untuk
transaksi jutaan rupiah, harga total harus selalu terlihat — bukan progressive disclosure.

🟡 U4 — Parameter capacity hilang di perjalanan

Form hero (home.blade.php:89) mengirim capacity. ApartmentController.php:54 memvalidasi & memfilternya dengan benar. Tapi form filter di
apartments/index.blade.php:15-49 tidak punya field capacity dan tidak punya hidden input untuk mempertahankannya. Jadi begitu user menyentuh filter apa pun di
halaman listing, kriteria kapasitas yang ia pilih di home hilang tanpa pemberitahuan. Sebaliknya max_price dan sort ada di listing tapi tidak di hero — dua
form pencarian dengan kemampuan berbeda.

🟡 U5 — Tidak ada indikator filter aktif dan tidak ada jumlah hasil

Halaman listing tidak menampilkan "Menampilkan 1-9 dari 12 unit" maupun chip filter aktif — padahal design/daftarunit.png punya keduanya. Setelah paginasi,
user tidak punya orientasi.

🟡 U6 — Perbandingan antar unit sulit

Kartu listing menampilkan bed/bath/m²/tamu, tapi tidak menampilkan fasilitas (ApartmentController.php:27 sudah eager-load facilities, jadi datanya tersedia
gratis dan tidak dipakai). Tidak ada wishlist/compare/save. Untuk keputusan sewa, user perlu buka setiap detail dalam tab terpisah.

🟡 U7 — Status Completed tampil seperti kegagalan

bookings/show.blade.php:35-39: hanya Confirmed dan Pending punya branch sendiri. BookingStatus punya 4 case. Jadi Cancelled dan Completed sama-sama jatuh ke
branch rose/merah. Booking yang sukses selesai ditampilkan dengan warna bahaya. bookings/index.blade.php:55-62 lebih baik (Cancelled punya branch sendiri,
Completed dapat badge slate netral) — dua halaman dengan logika status berbeda untuk data yang sama.

---

Visual Issues

🟠 V1 — Foto unit permanen abu-abu di mobile

apartment-card.blade.php:25: grayscale ... group-hover:grayscale-0. Efek ini bergantung sepenuhnya pada hover. Di perangkat sentuh tidak ada hover — semua
foto unit pilihan di home akan tetap abu-abu, selamanya, untuk mayoritas trafik. Di situs sewa apartemen, foto adalah alat jual utama. Ini keputusan editorial
yang wajar di desktop dan cacat total di mobile.

🟠 V2 — Pagination memakai desain pihak ketiga

apartments/index.blade.php:102 dan bookings/index.blade.php:83 memakai ->links() default. Saya periksa vendor/laravel/framework/.../tailwind.blade.php:
warnanya bertumpu pada varian dark: (dark:bg-gray-800, dark:text-gray-400, dark:focus:border-blue-700).

Di Tailwind v4, dark: default-nya prefers-color-scheme. Situs ini gelap tanpa syarat (app.css:54-66, color-scheme: dark tidak mempengaruhi media query).
Artinya: user dengan OS light mode akan melihat bar pagination putih dengan teks abu-abu di halaman gelap. Bahkan di dark mode, hasilnya gray-800 + focus ring
biru — dua warna yang tidak ada di palet mana pun.

Ini elemen ketiga yang muncul di halaman selain ink/gold dan slate/brand.

🟡 V3 — Aksen liar di halaman lama

text-rose-500 untuk ikon pin lokasi (apartments/index.blade.php:78, apartments/show.blade.php:24) dan to-teal-400 di logo auth (login:9, register:9). Merah
dan teal tidak ada di @theme. Teal khususnya adalah sisa palet lama yang sekarang bertabrakan langsung dengan emas.

🟡 V4 — Border radius: dua bahasa bentuk

Redesign memakai sudut tajam (0 radius) di .search-field, .empty-state, kartu apartemen, tombol menu — plus border-radius: 999px di .gold-button. Halaman lama
seragam rounded-xl/rounded-2xl/rounded-3xl. Sudut tajam + pill adalah pilihan editorial yang jelas dan konsisten; masalahnya halaman lama tidak mengikutinya.

🟡 V5 — Matematika grid fasilitas hanya benar untuk jumlah tertentu

home.blade.php:161-163: md:grid-cols-6, item index <3 span 2, sisanya span 3.

- 5 fasilitas → 2+2+2 / 3+3 ✅
- 4 fasilitas → 2+2+2 / 3 → satu sel menggantung di baris kedua ❌
- 2 fasilitas → 2+2 → grid berhenti di 4 dari 6 kolom, garis border putus ❌

Karena jumlah fasilitas dikelola admin, layout bisa rusak tanpa ada perubahan kode.

Terkait: HomeController.php:19 mengambil 8 fasilitas, view memakai 5; baris 13-17 mengambil 6 featured, home.blade.php:136 memakai 3. Query mengambil lebih
banyak dari yang dipakai.

🟢 V6 — Micro-typography terlalu kecil untuk positioning premium

.search-field\_\_label 0.58rem ≈ 9.3px (app.css:213), meta kartu text-[10px]/text-[11px], .gold-button 12px uppercase dengan letter-spacing. Kontrasnya lulus,
tapi ukurannya menuntut usaha untuk dibaca. Butuh visual inspection untuk memastikan seberapa mengganggu di layar nyata.

---

Responsive Issues

🟠 R1 — Semua input memicu auto-zoom iOS Safari

.search-field\_\_control font-size: 0.78rem (12.5px, app.css:224) dan semua input halaman lama text-sm (14px). Safari iOS otomatis zoom saat fokus pada input
<16px, dan tidak mengembalikan zoom setelahnya. Efek: begitu user menyentuh field pencarian di hero, layout melompat dan tetap ter-zoom. Terjadi di setiap
form di situs ini, termasuk form booking.

🟠 R2 — Overflow horizontal pada meta row (disembunyikan, bukan diperbaiki)

apartments/index.blade.php:82 — flex items-center gap-3 dengan 7 anak ("2 Bed • 1 Bath • 85 m² • Maks 4 Tamu"), tanpa flex-wrap.
bookings/index.blade.php:36 — flex items-center gap-4 dengan 3 span berisi tanggal, juga tanpa flex-wrap.

Di 320px, lebar konten kartu tinggal ~280px dan ~200px. app.css:64 menetapkan body { overflow-x: hidden } — ini berarti konten yang melimpah terpotong tanpa
scrollbar. Di kartu riwayat booking, yang terpotong adalah tanggal check-out. Informasi hilang secara senyap, dan justru lebih sulit ditemukan dalam testing
karena tidak ada scrollbar sebagai petunjuk.

Kartu baru (apartment-card.blade.php:46) sudah memakai flex-wrap — polanya sudah benar, hanya belum diterapkan ke halaman lama.

🟡 R3 — Search bar hero sempit di lebar tablet

home.blade.php:58: md:grid-cols-[1.25fr_1fr_1fr_auto] dengan tombol md:min-w-40 (160px). Di 768px, ruang tersedia ~704px; setelah tombol, tiga field berbagi
~530px → masing-masing ~170px, dengan label 9px dan <select> berisi nama kota. Kemungkinan besar sempit tapi masih fungsional — butuh visual inspection di
768px dan 820px.

🟡 R4 — Tinggi hero di mobile

home.blade.php:40: min-h-[720px] + pt-28 + mt-14 pada form. Di iPhone SE (viewport ~667px), search bar hampir pasti di bawah fold. Karena search adalah aksi
utama di halaman ini, itu masalah. Butuh visual inspection untuk memastikan posisinya.

🟡 R5 — Panel booking sticky tenggelam di mobile

Di apartments/show.blade.php:117, form booking adalah kolom kedua grid — di mobile ia jatuh ke bawah deskripsi dan seluruh daftar fasilitas. sticky top-28
tidak berlaku di single column. Tidak ada sticky bottom bar berisi harga + CTA, padahal itu pola standar aplikasi booking mobile. Konversi mobile bergantung
pada user mau scroll melewati semua konten.

🟢 R6 — Panel admin Filament

Filament menangani responsive-nya sendiri dan tidak dikustomisasi di sini (app/Filament/Resources/\* hanya definisi form/tabel). Tidak ada risiko yang bisa
dinilai dari source; kalau perlu, cek /admin di tablet secara visual.

---

Accessibility Issues

Sudah dibahas di atas: C3 (kontras CTA, terukur gagal) dan C4 (error tidak inline, tidak terhubung ke field).

🟠 A1 — Toast tidak bisa ditutup

layouts/app.blade.php:27-54: fixed top-20 z-[60], tanpa tombol tutup, tanpa timeout. Wrapper pointer-events-none sehingga klik lolos ke bawahnya — tapi
toast-nya sendiri pointer-events-auto, jadi ia bisa menutupi konten di belakangnya secara permanen sampai navigasi berikutnya. Untuk user keyboard dan screen
reader, tidak ada cara mengabaikan pesan.

🟠 A2 — <select> tanpa label yang tersambung

home.blade.php:79 dan :89: <select> dibungkus <label class="search-field"> dengan teks di dalam <span>. Pembungkusan implisit memang valid HTML, tapi tidak
ada id/for dan tidak ada aria-label. Di apartments/index.blade.php:17-42, semua <label> sama sekali tidak punya for dan input-nya tidak punya id — tidak ada
asosiasi label sama sekali. Screen reader akan mengumumkan field-field itu tanpa nama, dan mengetuk label tidak memfokuskan input.

🟡 A3 — Perbedaan tombol vs link tidak konsisten

apartments/index.blade.php:93 — <a> yang di-style penuh sebagai tombol ("Lihat Detail").
apartments/show.blade.php:163 — <a> di dalam <form> yang di-style seperti tombol submit ("Masuk Untuk Booking"), padahal itu navigasi.
bookings/show.blade.php:103 — <button id="pay-button"> tanpa type, berada di luar form sehingga tidak melakukan submit, tapi default type="submit" adalah
jebakan yang tinggal menunggu.

🟡 A4 — Kontras teks sekunder

┌────────────────────────────────┬────────────────────────────────────┬────────┬─────────────────────────────┐
│ Lokasi │ Warna │ Rasio │ Status │
├────────────────────────────────┼────────────────────────────────────┼────────┼─────────────────────────────┤
│ bookings/show.blade.php:14 │ text-slate-500 #64748b di ink-950 │ 4.0:1 │ gagal AA (12px) │
├────────────────────────────────┼────────────────────────────────────┼────────┼─────────────────────────────┤
│ placeholder input halaman lama │ placeholder-slate-500 di slate-900 │ 3.8:1 │ gagal AA │
├────────────────────────────────┼────────────────────────────────────┼────────┼─────────────────────────────┤
│ text-ink-400 di ink-950 │ #748098 │ 4.79:1 │ lolos, tapi dipakai di 10px │
├────────────────────────────────┼────────────────────────────────────┼────────┼─────────────────────────────┤
│ text-ink-300 di ink-950 │ #98a2b7 │ 7.4:1 │ lolos │
└────────────────────────────────┴────────────────────────────────────┴────────┴─────────────────────────────┘

text-white/72 di atas gambar hero (home.blade.php:47) tidak bisa dinilai dari source — bergantung pada foto yang di-upload admin. Karena fotonya dinamis,
kontrasnya tidak terjamin untuk semua unit; overlay gradient di baris 37-38 membantu, tapi tidak memberi garansi.

🟡 A5 — Alt text

home.blade.php:28 — alt="" pada gambar hero. Ini benar (gambar dekoratif, teks sudah ada di <h1>). apartment-card.blade.php dan halaman lama sudah memakai
alt="{{ $apartment->title }}". Yang lemah: apartments/show.blade.php:44 memakai alt="Gallery" untuk setiap gambar galeri — tidak deskriptif dan berulang.

🟡 A6 — Fokus keyboard di menu mobile

app.js:5-26: menu dibuka/ditutup dengan toggle class hidden, tapi fokus tidak dipindahkan ke dalam menu dan tidak ada focus trap. Handler Escape terdaftar
global dan memanggil menuToggle.focus() bahkan saat menu sedang tertutup — artinya menekan Escape di mana pun di halaman akan memindahkan fokus ke tombol
hamburger.

🟢 A7 — Touch target

Bagian yang baru sudah benar: min-h-11 (44px) pada nav item, .gold-button min-height: 2.75rem, tombol hamburger h-11 w-11. Halaman lama lebih tipis:
apartments/index.blade.php:47 tombol Reset py-2 px-3 dengan text-xs ≈ tinggi 32px — di bawah minimum 44px, dan berdampingan langsung dengan tombol Filter.

---

Trust & Conversion Issues

🟠 T1 — Tidak ada konfirmasi apa pun selain satu halaman

Saya cek: tidak ada app/Mail, tidak ada app/Notifications, tidak ada pemanggilan Mail::/->notify() di seluruh app/. Jadi setelah membayar, satu-satunya bukti
reservasi yang dimiliki user adalah halaman invoice di browser. Tidak ada email, tidak ada bukti yang bisa disimpan atau ditunjukkan, tidak ada tombol
print/download.

Sementara itu bookings/show.blade.php:121 menyuruh user "Tunjukkan bukti invoice reservasi ini saat kedatangan di resepsionis" — bukti yang secara harfiah
tidak bisa ia bawa.

Missing information, bukan cacat UI.

🟠 T2 — Kebijakan pembatalan tidak ada, dan user tidak bisa membatalkan

BookingStatus::Cancelled hanya pernah di-set dari webhook Midtrans (PaymentController.php:27-30) atau manual lewat Filament. Tidak ada aksi cancel user-facing
di mana pun. Tidak ada teks kebijakan pembatalan/refund di seluruh view.

design/bookingform.png justru menjanjikan "JAMINAN SANTHOSA — Kebijakan pembatalan gratis hingga 24 jam sebelum check-in" — janji yang tidak didukung kode
maupun UI. Jangan implementasikan teks itu sebelum fiturnya ada.

🟠 T3 — Footer tidak punya satu pun trust signal

site-footer.blade.php hanya memuat: kalimat besar "Mulai Perjalanan Anda", 4 link navigasi, daftar kota, copyright. Tidak ada kontak, alamat, nomor telepon,
halaman bantuan, syarat & ketentuan, kebijakan privasi, ataupun badge pembayaran.

Mockup punya kolom "Informasi" berisi 4 link (Tentang Kami, Kebijakan Privasi, Syarat & Ketentuan, Hubungi Kami) plus ikon sosial. Untuk situs yang meminta
transfer jutaan rupiah ke entitas yang tidak dikenal, tidak adanya cara menghubungi manusia adalah masalah konversi, bukan masalah polish. Terutama karena
tidak ada email konfirmasi (T1) — kalau ada yang salah, user tidak punya jalur apa pun.

🟡 T4 — Keamanan pembayaran hampir tidak disebut

Midtrans hanya disebut satu kali, di bookings/show.blade.php:96, dengan text-xs text-slate-300. Di halaman detail — tempat keputusan booking benar-benar
diambil — tidak ada penyebutan sama sekali soal bagaimana pembayaran diproses atau apakah kartu akan langsung ditagih. Mockup memakai "DANA ANDA TIDAK AKAN
DITARIK SEKARANG" persis di bawah CTA; ini teknik reassurance standar dan penerapannya di sini akurat (booking dibuat pending lebih dulu).

🟡 T5 — Error pembayaran ditangani dengan alert()

bookings/show.blade.php:154: alert("Pembayaran gagal atau dibatalkan."). Dialog browser native di titik paling sensitif dalam funnel — terlihat seperti pesan
error sistem, bukan bagian dari produk. Penanganan state-nya sendiri sudah baik (guard isProcessing, tombol di-enable kembali di onError/onClose — baris
134-167 rapi), hanya presentasinya yang tidak sesuai.

🟡 T6 — Halaman pembayaran tidak menampilkan status pembayaran

bookings/show.blade.php bercabang pada $booking->status saja. PaymentStatus punya 5 case (pending, settlement, expire, cancel, failed) dan Payment
di-eager-load (BookingController.php:104) — tapi tidak pernah ditampilkan. Untuk pembayaran expire atau failed, booking jadi cancelled dan user melihat badge
merah bertuliskan "Cancelled" tanpa penjelasan mengapa dan tanpa jalan untuk mencoba lagi. Jalur pemulihan setelah pembayaran gagal tidak ada.

🟢 T7 — Yang sudah cukup transparan

Harga per malam terlihat di kartu, listing, dan detail. Rincian biaya di invoice (bookings/show.blade.php:69-87) jujur dan tidak ada biaya tersembunyi — total
= tarif sewa, tidak lebih. Kode reservasi ditampilkan monospace dan menonjol. Waktu check-in/check-out konkret (14:00/12:00 WIB, baris 73 & 77) — detail
kecil yang justru meningkatkan kepercayaan.

---

Data/UI Issues

🔴 D1 — main_image tidak di-resolve di 5 tempat

Lihat C2. Ini isu Data/UI paling serius: UI mengasumsikan bentuk data yang tidak dihasilkan oleh admin panel-nya sendiri.

🟠 D2 — Mockup meminta field yang tidak ada di database

Saya verifikasi terhadap migration. Yang muncul di design/detail.png dan bookingform.png tapi tidak ada di skema:

┌───────────────────────────────────────────┬───────────────────────────────────────────────────────────────────┐
│ Elemen mockup │ Status data │
├───────────────────────────────────────────┼───────────────────────────────────────────────────────────────────┤
│ Rating ★4.9 (128 Ulasan) │ Tidak ada tabel/kolom reviews │
├───────────────────────────────────────────┼───────────────────────────────────────────────────────────────────┤
│ "Biaya Layanan & Pajak IDR 540.000" │ Tidak ada; total_price = malam × tarif saja │
├───────────────────────────────────────────┼───────────────────────────────────────────────────────────────────┤
│ "Jumlah Tamu: 2 Orang" pada booking │ bookings tidak punya kolom guests │
├───────────────────────────────────────────┼───────────────────────────────────────────────────────────────────┤
│ Peta + "Tempat Terdekat" + "Transportasi" │ Tidak ada lat/lng maupun kolom POI │
├───────────────────────────────────────────┼───────────────────────────────────────────────────────────────────┤
│ "+12 Foto Lainnya" │ images JSON nullable; belum tentu ada isinya │
├───────────────────────────────────────────┼───────────────────────────────────────────────────────────────────┤
│ Kategori Studio/1BR/2BR/Penthouse │ Tidak ada kolom kategori (hanya bedrooms integer) │
├───────────────────────────────────────────┼───────────────────────────────────────────────────────────────────┤
│ Wishlist (ikon hati) │ Tidak ada tabel favorites │
├───────────────────────────────────────────┼───────────────────────────────────────────────────────────────────┤
│ Chip "Fasilitas Populer" sebagai filter │ Relasi ada, tapi controller tidak memfilter berdasarkan fasilitas │
└───────────────────────────────────────────┴───────────────────────────────────────────────────────────────────┘

Ini bukan masalah pada UI sekarang — tapi akan menjadi masalah begitu mockup diimplementasikan literal. Kalau perlu, putuskan sekarang: mana yang di-drop dan
mana yang perlu perubahan skema. Menampilkan rating hardcode ★4.9 di produk yang menerima uang adalah kebohongan pada user.

🟠 D3 — Rentang tanggal yang ter-booking tidak pernah ditampilkan

Lihat U1. $bookedDates sudah dihitung dan dikirim, lalu dibuang.

🟡 D4 — Kombinasi status yang tidak ter-render dengan benar

Lihat U7 (Completed tampil merah) dan T6 (PaymentStatus tidak pernah dipakai di UI).

🟡 D5 — ApartmentStatus::Maintenance tidak terlihat di mana pun

Semua query publik memfilter status = available (ApartmentController.php:27, :87; HomeController.php:14). Berarti unit yang di-maintenance hilang begitu saja
dari katalog. Ini pilihan yang sah, tapi konsekuensinya: link detail yang sudah dibagikan atau di-bookmark akan menghasilkan 404 ketika admin mengubah status
unit (firstOrFail()). Tidak ada halaman "unit sedang tidak tersedia". Untuk bookings/show, apartemen di-load lewat relasi sehingga tetap tampil — konsisten,
kebetulan.

🟡 D6 — Deskripsi tanpa batas panjang

apartments/show.blade.php:96 me-render nl2br(e($description)) penuh dalam kolom prose. Kolom description bertipe text tanpa batas di form Filament — deskripsi
4000 kata akan mendorong seluruh layout tanpa "Baca selengkapnya". Escaping-nya benar (e() sebelum nl2br, praktik yang tepat).

🟡 D7 — Ikon fasilitas kemungkinan besar akan generik

facility-icon.blade.php mendukung 8 nama (wifi, pool, gym, tv, shield, parking, kitchen, balcony) dengan fallback sparkles. FacilityResource.php:32-34 adalah
TextInput bebas dengan helper "Heroicon name or text icon indicator" — helper text ini salah, karena nama Heroicon (heroicon-o-wifi) tidak akan cocok dengan
case mana pun. Admin yang menuruti instruksi akan mendapat sparkles untuk semua fasilitas. Berarti section "Fasilitas Unggulan" di home berisiko tampil
sebagai lima ikon identik.

🟢 D8 — Data kosong sudah ditangani dengan baik

Home menangani featured kosong dan facilities kosong; listing dan riwayat booking punya empty state; footer punya @forelse fallback untuk kota
(site-footer.blade.php:31). popularCities di-share lewat view composer (AppServiceProvider.php:53-61), jadi footer bekerja di semua halaman — layout memang
punya guard ?? collect(), tapi tidak diperlukan. Baik.

---

Quick Wins

Perubahan kecil, dampak besar — diurutkan berdasarkan rasio dampak/usaha:

1. Hapus dua string copy internal (home.blade.php:191, :158). Dua baris teks, langsung menghilangkan sinyal "situs belum jadi" dari halaman depan.
2. Ganti text-white → text-ink-950 pada 6 tombol brand-gradient. Enam kelas, memperbaiki kegagalan kontras 2.6:1 pada seluruh CTA utama.
3. Hapus grayscale dari apartment-card.blade.php:25 (atau ubah jadi md:grayscale). Satu kelas, memulihkan foto berwarna untuk semua user mobile.
4. Tambah flex-wrap di apartments/index.blade.php:82 dan bookings/index.blade.php:36. Satu kelas per baris, menghentikan hilangnya informasi di mobile.
5. Tambah value="{{ old(...) }}" pada tiga field form booking. Menghentikan hilangnya input saat tanggal bentrok — titik gagal paling sering di funnel.
6. Tambah hidden input capacity di form filter listing. Satu baris, menjaga kriteria pencarian user.
7. Naikkan font-size input ke 16px di viewport mobile (app.css:224). Satu media query, menghilangkan auto-zoom iOS di seluruh situs.
8. Tambah type="button" pada bookings/show.blade.php:103. Menutup jebakan submit sebelum sempat jadi bug.
9. Publish view pagination dan warnai sesuai palet. Menghapus komponen off-brand ketiga dari dua halaman.
10. Tambah Completed sebagai branch tersendiri di bookings/show.blade.php:35. Menghentikan booking sukses tampil sebagai kegagalan.

Nomor 1–8 semuanya perubahan satu baris dan menyelesaikan sebagian besar Critical Issues.

---

Priority Roadmap

P0 — wajib diperbaiki sebelum production

- C1 copy internal di halaman depan (home.blade.php:191, :158)
- C2 resolusi main_image di 5 view — gambar rusak untuk semua data non-seeder
- C3 kontras CTA gagal WCAG AA di 6 lokasi
- C4 error inline + old() pada form booking
- C5 tuntaskan redesign 6 halaman, atau rollback shell agar konsisten

P1 — sangat disarankan

- V1 grayscale di mobile
- U1 tampilkan tanggal ter-booking ($bookedDates sudah ada) & pakai endpoint availability yang sudah ditulis
- U2 halaman review + step indicator sebelum booking dibuat
- U3 total biaya selalu terlihat
- R1 auto-zoom iOS · R2 overflow mobile
- V2 pagination sesuai palet
- T1 email konfirmasi + invoice yang bisa disimpan
- T3 kontak & halaman legal di footer
- A2 asosiasi label form di listing

P2 — improvement

- U4 capacity hilang · U5 jumlah hasil & chip filter aktif · U6 fasilitas di kartu listing
- U7/T6 rendering status lengkap (4 booking status, 5 payment status) + jalur pemulihan pembayaran gagal
- T2 kebijakan pembatalan (dan/atau fitur cancel)
- T5 ganti alert() dengan UI dalam produk
- R5 sticky booking bar mobile
- V5 grid fasilitas yang tahan terhadap jumlah item apa pun
- D7 ubah field ikon fasilitas menjadi Select yang terbatas pada 8 nama yang didukung
- D5 halaman "unit tidak tersedia" alih-alih 404

P3 — polish

- V6/A4 micro-typography & kontras teks sekunder
- A1 tombol tutup toast · A6 focus trap menu mobile
- V3 buang aksen rose/teal
- D6 truncate deskripsi dengan "Baca selengkapnya"
- Hapus resources/views/welcome.blade.php (tanpa route, desain ketiga)
- Rapikan query yang mengambil lebih banyak dari yang dipakai (HomeController.php:13-19, $facilities yang tidak terpakai di apartments.index)
- alt deskriptif pada galeri (apartments/show.blade.php:44)

---

Final Verdict

1. Apakah redesign sudah layak?

Arahnya benar, eksekusinya belum selesai — dan belum layak production.

Yang penting dipisahkan: kualitas desain baru dan kualitas produk. Desain baru bagus. Home page punya karakter, tipografinya punya niat (Playfair + Inter,
sudut tajam, aksen emas terkendali), dan CSS-nya terstruktur dengan design token serta komponen yang bisa dipakai ulang. Kalau seluruh situs terlihat seperti
home page, penilaian saya akan sangat berbeda.

Tapi produknya adalah 7 halaman, dan 6 di antaranya belum ikut pindah. Sekarang situs ini punya tiga bahasa visual sekaligus: ink/gold (shell + home),
slate/brand (semua halaman lain, dengan warna brand yang sudah di-remap ke emas sehingga bertabrakan dengan teal sisa palet lama), dan gray-800/blue milik
pagination default Laravel. Jahitannya muncul di transisi yang paling merugikan — tepat setelah user menekan "Cari".

Dan produksi kode yang bagus di satu tempat tidak menyelamatkan yang lain: apartment-card.blade.php menangani path gambar dengan benar, sementara lima view
lain memakai nilai DB mentah dan akan menampilkan gambar rusak begitu admin meng-upload unit pertamanya. Konsistensi bukan sekadar soal tampilan di sini —
konsistensi adalah yang membuat perbaikan berlaku menyeluruh.

2. Tiga masalah terbesar

Pertama — redesign hanya 1 dari 7 halaman. Mockup untuk halaman lain sudah ada di resources/views/design/, komponennya (.gold-button, .search-field,
.empty-state, x-apartment-card) sudah siap. Ini pekerjaan yang belum dikerjakan, bukan keputusan desain. Kondisi setengah jalan lebih merusak kepercayaan
daripada konsisten-tapi-biasa.

Kedua — gambar akan rusak di produksi. apartments/index:64, apartments/show:38,44, bookings/index:30, bookings/show:48 memakai main_image mentah; Filament
menyimpan path relatif. Tidak terlihat sekarang hanya karena seeder memakai URL Unsplash absolut. Situs sewa apartemen tanpa foto tidak menghasilkan booking,
dan gejalanya menyesatkan: home normal, katalog rusak.

Ketiga — jalur kegagalan tidak dirancang. Nol @error di seluruh view; error muncul sebagai toast melayang di atas viewport, jauh dari field yang salah, tanpa
bisa ditutup. Form booking tidak memakai old() meski controller sudah mengirim withInput(). Tanggal bentrok adalah skenario paling umum di produk ini, dan
penanganannya adalah pesan di tempat yang salah plus form yang direset. Ditambah tidak ada email konfirmasi dan tidak ada kontak di footer — kalau ada yang
salah, user tidak punya jalan keluar.

3. Tiga hal terbaik

Pertama — arsitektur CSS-nya. app.css memakai design token di @theme, komponen yang bisa dipakai ulang, prefers-reduced-motion dihormati, :focus-visible
global. Ini fondasi yang membuat penyelesaian 6 halaman sisanya jauh lebih murah daripada terlihat.

Kedua — aksesibilitas struktural pada bagian yang baru. skip-link yang benar-benar berfungsi, aria-live pada region flash, aria-expanded/aria-controls pada
menu mobile, Escape mengembalikan fokus, semua touch target ≥44px. Ini disengaja. Ironisnya justru menegaskan poin utama audit ini: perhatian yang sama belum
sampai ke halaman lama.

Ketiga — arah visual yang punya pendirian. Sudut tajam, satu aksen emas, serif untuk display, foto grayscale-ke-warna, .gold-button dengan kontras 10.2:1. Ini
bukan template Bootstrap generik — ada seseorang yang mengambil keputusan. Kontras kualitasnya dengan halaman lama justru menjadi argumen paling kuat untuk
menyelesaikannya, bukan membatalkannya.

4. Apa yang harus diperbaiki sebelum production

Minimum absolut — kelima P0:

1. Hapus copy internal dari halaman depan (2 baris)
2. Perbaiki resolusi main_image di 5 view (angkat pola yang sudah ada jadi accessor)
3. Perbaiki kontras 6 CTA (text-white → text-ink-950)
4. Error inline + old() pada form booking
5. Pilih satu: tuntaskan 6 halaman, atau rollback shell

Nomor 1–4 semuanya perubahan kecil dan bisa selesai cepat. Nomor 5 adalah keputusan, bukan tugas — dan itu keputusan Anda. Rekomendasi saya: tuntaskan, karena
mockup dan komponennya sudah siap dan desainnya memang bagus.

Sangat disarankan menyertakan juga: hapus grayscale di mobile (satu kelas, memulihkan foto untuk mayoritas trafik), dan tambahkan kontak di footer (tanpa
email konfirmasi, footer adalah satu-satunya jalur bantuan yang dimiliki user).

---

Butuh visual inspection — tidak bisa dinilai dari source code: kualitas & komposisi foto hero, keterbacaan text-white/72 di atas gambar yang di-upload admin
(kontrasnya berubah per unit), kesempitan nyata search bar di 768–820px, apakah search bar hero ada di bawah fold pada layar mobile kecil, dan tampilan panel
admin Filament di tablet.

Tidak ada kode yang diubah. Audit lengkap juga tersimpan sebagai roadmap berprioritas di plan file untuk referensi saat perbaikan.
