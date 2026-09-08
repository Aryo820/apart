@extends('layouts.app')

@php
    $support = config('support');
@endphp

@section('title', 'Kebijakan Privasi — ' . $support['business_name'])
@section('meta_description', 'Data yang dikumpulkan ' . $support['business_name'] . ', tujuan penggunaannya, dan pihak ketiga yang terlibat dalam proses reservasi.')

@section('content')
    <section class="border-b border-ink-300 bg-paper-200">
        <div class="site-container py-12 sm:py-14">
            <div class="mx-auto max-w-3xl">
                <nav class="annotation flex items-center gap-2 uppercase" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}" class="transition-colors hover:text-blueprint-700">Beranda</a>
                    <span aria-hidden="true">/</span>
                    <span class="text-ink-700" aria-current="page">Kebijakan privasi</span>
                </nav>

                <h1 class="mt-6 text-4xl font-bold tracking-[-0.02em] text-ink-900 sm:text-5xl">
                    Kebijakan Privasi
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
                    Halaman ini menjelaskan data yang benar-benar dikumpulkan dan dipakai oleh sistem
                    {{ $support['business_name'] }} — tidak lebih. Kami tidak mengumpulkan data di luar daftar di
                    bawah ini.
                </p>

                <h2>1. Data yang kami kumpulkan</h2>
                <p><strong>Data akun</strong>, yang Anda isi sendiri saat mendaftar:</p>
                <ul>
                    <li>Nama</li>
                    <li>Alamat email — sekaligus menjadi identitas untuk masuk</li>
                    <li>Nomor telepon</li>
                    <li>Password, disimpan dalam bentuk hash dan tidak dapat dibaca kembali oleh kami</li>
                </ul>

                <p><strong>Data reservasi</strong>, yang terbentuk saat Anda memesan:</p>
                <ul>
                    <li>Unit yang dipesan</li>
                    <li>Tanggal check-in dan check-out serta jumlah malam</li>
                    <li>Total biaya</li>
                    <li>Catatan khusus, bila Anda mengisinya</li>
                    <li>Status reservasi dan waktu pembuatannya</li>
                </ul>

                <p><strong>Data transaksi</strong>, yang dicatat dari proses pembayaran:</p>
                <ul>
                    <li>Nominal pembayaran dan status pembayaran</li>
                    <li>Nomor transaksi dan jenis metode pembayaran dari Midtrans</li>
                    <li>Salinan pemberitahuan status yang dikirim Midtrans ke sistem kami</li>
                </ul>
                <p>
                    Kami <strong>tidak</strong> menerima maupun menyimpan nomor kartu, CVV, PIN, atau kredensial
                    perbankan Anda. Data tersebut dimasukkan pada halaman Midtrans dan tidak melewati server kami.
                </p>

                <h2>2. Cara kami menggunakannya</h2>
                <ul>
                    <li>Membuat dan menampilkan reservasi Anda, termasuk riwayatnya.</li>
                    <li>Memproses pembayaran dan memperbarui status reservasi setelah pembayaran dilaporkan.</li>
                    <li>Mengirim nama, email, dan nomor telepon Anda ke Midtrans sebagai data pemesan pada transaksi.</li>
                    <li>Memverifikasi identitas Anda saat kedatangan di unit.</li>
                    <li>Menjawab pertanyaan atau keluhan yang Anda sampaikan ke kontak bantuan.</li>
                </ul>
                <p>
                    Kami tidak menjual data Anda, tidak menggunakannya untuk pemasangan iklan, dan tidak
                    menyusun profil perilaku. Sistem ini juga tidak memasang alat analitik maupun pelacak pihak ketiga.
                </p>

                <h2>3. Pihak ketiga yang terlibat</h2>
                <ul>
                    <li>
                        <strong>Midtrans</strong> — memproses pembayaran. Menerima nama, email, nomor telepon, kode
                        reservasi, nama unit, dan nominal transaksi Anda. Halaman pembayarannya dimuat dari server
                        Midtrans.
                    </li>
                    <li>
                        <strong>Google Fonts</strong> — memuat huruf yang dipakai situs ini. Browser Anda mengambil
                        berkasnya langsung dari server Google, sehingga alamat IP Anda diketahui oleh Google.
                    </li>
                </ul>
                <p>Selain keduanya, tidak ada pihak lain yang menerima data Anda dari sistem ini.</p>

                <h2>4. Cookie</h2>
                <p>
                    Kami memakai cookie hanya untuk hal yang membuat situs berfungsi: menjaga sesi login Anda dan
                    melindungi formulir dari penyalahgunaan lintas situs. Bila Anda mencentang
                    <em>Ingat saya di perangkat ini</em> saat masuk, sesi Anda disimpan lebih lama. Tidak ada cookie
                    iklan atau pelacakan.
                </p>

                <h2>5. Penyimpanan dan akses</h2>
                <p>
                    Data reservasi dan transaksi disimpan selama akun Anda aktif, karena keduanya adalah catatan
                    pemesanan yang perlu tetap dapat ditelusuri. Pengelola {{ $support['business_name'] }} dapat
                    melihat data reservasi dan pembayaran melalui panel administrasi untuk keperluan operasional.
                </p>

                <h2>6. Hak Anda</h2>
                <p>
                    Anda dapat meminta salinan, koreksi, atau penghapusan data Anda dengan menghubungi kontak di
                    bawah. Perlu diketahui bahwa penghapusan data reservasi yang masih aktif atau sudah dibayar dapat
                    membatalkan reservasi tersebut.
                </p>

                {{-- Ditandai eksplisit: mekanismenya belum ada di aplikasi, jadi
                     jangan dituliskan seolah-olah sudah otomatis. --}}
                <div class="mt-5 border border-blueprint-300 bg-blueprint-50 px-4 py-3.5">
                    <p class="font-annotation text-xs font-bold uppercase tracking-[0.08em] text-blueprint-800">Perlu dikonfigurasi</p>
                    <p class="mt-2 text-xs leading-6 text-ink-700">
                        Permintaan ekspor dan penghapusan data saat ini dilayani secara manual melalui kontak bantuan;
                        belum ada tombol mandiri di dalam akun. Tenggat penanganan permintaan serta lama penyimpanan
                        data setelah akun ditutup belum ditetapkan dan perlu diisi oleh pengelola.
                    </p>
                </div>

                <h2>7. Kontak</h2>
                <ul>
                    <li>Email: <a href="mailto:{{ $support['email'] }}">{{ $support['email'] }}</a></li>
                    <li>Telepon: <a href="tel:{{ $support['phone_tel'] }}">{{ $support['phone'] }}</a></li>
                    <li>Jam layanan: {{ $support['hours'] }}</li>
                </ul>
                <p>
                    Lihat juga <a href="{{ route('legal.terms') }}">Syarat &amp; Ketentuan</a> untuk ketentuan
                    pemesanan dan pembayaran.
                </p>
            </div>
        </div>
    </section>
@endsection
