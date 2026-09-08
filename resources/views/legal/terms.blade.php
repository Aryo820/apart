@extends('layouts.app')

@php
    $support = config('support');

    // Diturunkan dari config, bukan diketik di teks: batas waktu pembayaran yang
    // disebut dokumen ini selalu sama dengan yang dipakai scope, command, dan
    // gateway (config/booking.php).
    $paymentDeadlineHours = (int) round(config('booking.payment_expiry_minutes') / 60);
@endphp

@section('title', 'Syarat & Ketentuan — ' . $support['business_name'])
@section('meta_description', 'Ketentuan pemesanan, pembayaran, dan pembatalan untuk reservasi apartemen di ' . $support['business_name'] . '.')

@section('content')
    <section class="border-b border-ink-300 bg-paper-200">
        <div class="site-container py-12 sm:py-14">
            <div class="mx-auto max-w-3xl">
                <nav class="annotation flex items-center gap-2 uppercase" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}" class="transition-colors hover:text-blueprint-700">Beranda</a>
                    <span aria-hidden="true">/</span>
                    <span class="text-ink-700" aria-current="page">Syarat &amp; ketentuan</span>
                </nav>

                <h1 class="mt-6 text-4xl font-bold tracking-[-0.02em] text-ink-900 sm:text-5xl">
                    Syarat &amp; Ketentuan
                </h1>
                <p class="annotation mt-4 uppercase">
                    Terakhir diperbarui {{ $support['legal_updated_at'] }}
                </p>
            </div>
        </div>
    </section>

    <section class="bg-paper-100 py-12 sm:py-16">
        <div class="site-container">
            <div class="mx-auto max-w-3xl border border-ink-200 bg-paper-50 p-6 sm:p-10 legal-prose">
                <p>
                    Dokumen ini menjelaskan cara kerja reservasi di {{ $support['business_name'] }} sebagaimana yang
                    berjalan pada sistem saat ini. Dengan membuat reservasi, Anda menyatakan telah membaca dan
                    menyetujui ketentuan di bawah ini.
                </p>

                <h2>1. Ketentuan pemesanan</h2>
                <p>
                    Reservasi hanya dapat dibuat oleh pengguna yang telah masuk ke akunnya. Setiap reservasi
                    memerlukan tanggal check-in dan check-out, dengan durasi minimal satu malam. Waktu check-in
                    adalah <strong>14.00 WIB</strong> dan check-out <strong>12.00 WIB</strong>.
                </p>
                <p>
                    Setelah reservasi dibuat, sistem menerbitkan <strong>kode reservasi</strong> yang menjadi acuan
                    tunggal untuk seluruh komunikasi mengenai pesanan tersebut.
                </p>

                <h2>2. Ketersediaan unit</h2>
                <p>
                    Ketersediaan diperiksa dua kali: saat Anda memilih tanggal pada halaman unit, dan sekali lagi
                    pada saat reservasi disimpan. Pemeriksaan kedua bersifat final. Jika tanggal yang Anda pilih
                    sudah diambil pengguna lain di antara kedua langkah tersebut, reservasi tidak akan dibuat dan
                    Anda diminta memilih tanggal lain.
                </p>
                <p>
                    Hanya unit dengan status tersedia yang dapat dipesan. Unit yang sedang dalam pemeliharaan tidak
                    tampil di katalog dan tidak dapat direservasi.
                </p>

                <h2>3. Pembayaran</h2>
                <p>
                    Seluruh pembayaran diproses oleh <strong>Midtrans</strong> sebagai penyedia gerbang pembayaran.
                    Detail kartu dan kredensial pembayaran Anda dimasukkan pada halaman Midtrans dan tidak dikirim
                    ke maupun disimpan di server {{ $support['business_name'] }}.
                </p>
                <p>
                    Total yang ditagihkan sama dengan jumlah malam dikalikan tarif per malam yang tercantum pada
                    halaman unit saat reservasi dibuat. Tidak ada biaya layanan atau biaya tambahan lain yang
                    ditambahkan oleh sistem.
                </p>

                <h2>4. Status menunggu pembayaran</h2>
                <p>
                    Reservasi yang baru dibuat berstatus <strong>menunggu pembayaran</strong>. Pada status ini
                    tanggal yang Anda pilih sudah dikunci dan tidak dapat dipesan pengguna lain, tetapi reservasi
                    <strong>belum terkonfirmasi</strong>. Konfirmasi terjadi otomatis setelah Midtrans melaporkan
                    pembayaran diterima.
                </p>
                <p>
                    Batas waktu pembayaran adalah <strong>{{ $paymentDeadlineHours }} jam</strong> sejak reservasi
                    dibuat. Tanggal dan jam persisnya tercantum pada halaman reservasi Anda. Sesi pembayaran Midtrans
                    berakhir pada waktu yang sama — keduanya tidak berjalan terpisah.
                </p>
                <p>
                    Selama batas waktu belum lewat, Anda dapat membuka kembali halaman reservasi dari menu
                    <em>Booking saya</em> dan melanjutkan pembayaran. Setelah batas waktu lewat, reservasi menjadi
                    kedaluwarsa dan pembayarannya tidak dapat dilanjutkan lagi; tanggalnya kembali tersedia untuk tamu
                    lain dan Anda perlu membuat reservasi baru.
                </p>

                <h2>5. Pembatalan</h2>
                <p>
                    Selama reservasi masih berstatus <strong>menunggu pembayaran</strong> dan batas waktunya belum
                    lewat, Anda dapat membatalkannya sendiri melalui tombol <em>Batalkan reservasi</em> pada halaman
                    reservasi. Pembatalan pada tahap ini <strong>tidak menimbulkan tagihan maupun potongan</strong>,
                    karena belum ada pembayaran yang diterima. Tanggalnya langsung kembali tersedia untuk tamu lain,
                    dan reservasi yang sudah dibatalkan tidak dapat diaktifkan kembali — Anda perlu memesan ulang.
                </p>
                <p>
                    Pembatalan mandiri tidak tersedia untuk reservasi yang sudah dibayar, yang sudah kedaluwarsa, atau
                    yang bukan milik akun Anda.
                </p>
                <p>
                    Untuk reservasi yang <strong>sudah dibayar</strong>, pembatalan dan pengembalian dana tidak
                    diproses otomatis oleh sistem. Hubungi kami melalui
                    <a href="mailto:{{ $support['email'] }}">{{ $support['email'] }}</a> dengan menyertakan kode
                    reservasi Anda. Permintaan ditinjau kasus per kasus, hasilnya kami sampaikan melalui email atau
                    telepon yang tercatat pada akun Anda, dan pengembalian dana dilakukan melalui Midtrans ke metode
                    pembayaran yang Anda gunakan.
                </p>

                <h2>6. Kegagalan pembayaran</h2>
                <p>
                    Jika Midtrans melaporkan pembayaran <strong>gagal, ditolak, atau dibatalkan</strong>, reservasi
                    terkait berubah menjadi <strong>dibatalkan</strong> dan tanggalnya dilepas kembali ke katalog.
                    Jika batas waktu pembayaran lewat tanpa pembayaran yang diterima, reservasi berubah menjadi
                    <strong>kedaluwarsa</strong>. Kedua status ini dibedakan pada riwayat Anda: “dibatalkan” berarti
                    ada yang membatalkan, “kedaluwarsa” berarti waktunya habis. Pada kedua keadaan tersebut Anda dapat
                    memesan ulang unit yang sama melalui tautan pada halaman reservasi.
                </p>
                <p>
                    Reservasi yang berakhir karena pembayaran gagal atau batas waktu lewat <strong>tidak menimbulkan
                    tagihan</strong>. Jika dana Anda tetap terpotong sementara reservasi tampil dibatalkan atau
                    kedaluwarsa, hubungi kami dengan menyertakan kode reservasi.
                </p>
                <p>
                    Bila percobaan pembayaran pertama ditolak dan Anda mencoba lagi pada sesi Midtrans yang sama lalu
                    berhasil, pembayaran itu tetap kami proses dan reservasi dikonfirmasi kembali — sepanjang tanggalnya
                    belum diambil tamu lain.
                </p>
                <p>
                    Dalam kasus yang jarang terjadi, pembayaran dapat sampai ke sistem kami setelah tanggalnya dipesan
                    tamu lain, misalnya karena notifikasi dari Midtrans tertunda. Reservasi Anda
                    <strong>tidak</strong> dikonfirmasi dalam keadaan itu, karena satu unit dan tanggal yang sama tidak
                    boleh dipegang dua reservasi. Halaman reservasi Anda akan menampilkan keadaan tersebut, pembayaran
                    Anda tetap tercatat, dan kami menindaklanjuti pengembalian dananya.
                </p>

                <h2>7. Tanggung jawab pengguna</h2>
                <ul>
                    <li>Memastikan nama, email, dan nomor telepon pada akun Anda benar dan dapat dihubungi.</li>
                    <li>Menjaga kerahasiaan kredensial akun; reservasi yang dibuat dari akun Anda dianggap dibuat oleh Anda.</li>
                    <li>Memeriksa unit, tanggal, dan total biaya pada halaman reservasi sebelum melanjutkan pembayaran.</li>
                    <li>Menunjukkan kode reservasi saat kedatangan, beserta identitas yang sesuai dengan data pemesan.</li>
                    <li>Menggunakan unit sesuai kapasitas yang tercantum pada halaman unit.</li>
                </ul>

                <h2>8. Perubahan informasi apartemen</h2>
                <p>
                    Deskripsi, foto, fasilitas, tarif, dan status ketersediaan unit dikelola oleh pengelola dan dapat
                    berubah kapan saja. Perubahan tersebut <strong>tidak mengubah</strong> reservasi yang sudah
                    dibuat: tarif dan total biaya yang berlaku adalah yang tercatat pada reservasi Anda.
                </p>
                <p>
                    Kami berupaya menjaga keakuratan informasi pada katalog, namun kesalahan penulisan atau data yang
                    belum diperbarui dapat terjadi. Bila terdapat perbedaan antara katalog dan kondisi unit, silakan
                    hubungi kami.
                </p>

                <h2>9. Kontak bantuan</h2>
                <p>
                    Untuk pertanyaan mengenai reservasi, pembayaran, atau ketentuan ini:
                </p>
                <ul>
                    <li>Email: <a href="mailto:{{ $support['email'] }}">{{ $support['email'] }}</a></li>
                    <li>Telepon: <a href="tel:{{ $support['phone_tel'] }}">{{ $support['phone'] }}</a></li>
                    <li>Jam layanan: {{ $support['hours'] }}</li>
                </ul>
                <p>
                    Sertakan kode reservasi Anda agar kami dapat menindaklanjuti lebih cepat. Lihat juga
                    <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a> untuk penjelasan data yang kami
                    kumpulkan.
                </p>
            </div>
        </div>
    </section>
@endsection
