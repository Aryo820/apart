<!DOCTYPE html>
<html lang="id" class="h-full scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Santhosa — Apartemen Terkurasi, Langsung dari Pemilik')</title>
    <meta name="description" content="@yield('meta_description', 'Unit apartemen terkurasi langsung dari pemilik: spesifikasi terukur, harga transparan, reservasi terkonfirmasi otomatis.')">
    {{-- Halaman yang isinya bukan penawaran aktif (mis. unit sedang ditutup)
         menimpanya dengan noindex lewat @section('robots'). --}}
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <meta name="theme-color" content="#f6f4ed">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Fragment+Mono&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @stack('styles')
</head>

<body class="min-h-full bg-paper-100 font-sans text-ink-700 antialiased">
    <!--
        DIRECTION CONTRACT — Denah & Brosur Teknis (seed 1c4b6ffd, form 1/7, IMPECCABLE'S PICK)
        THESIS: Setiap unit adalah lembar brosur teknis yang diukur, bukan kartu iklan —
        transparansi sampai ke meter, dan layout kategori (hero foto + grid kartu generik) ditolak.
        OWN-WORLD: Kertas drafting terang #f6f4ed, tinta grafit, biru drafting untuk struktur,
        merah dimensi khusus aksi/pengukuran; plat ber-paspartu, arsiran, tabel spesifikasi,
        title block; Archivo (masthead lebar) + Fragment Mono untuk anotasi data.
        STORY: Pengunjung paham bahwa unit dikurasi langsung dari pemilik, spesifikasinya jujur
        terukur, dan memesan = mengisi formulir teknis yang dikonfirmasi otomatis.
        FIRST VIEWPORT: Masthead brosur di title block nav; di bawahnya judul besar + plat foto
        unit unggulan yang diukur garis dimensi merah hidup; formulir pencarian teknis di kiri;
        CTA merah "Cari unit" di dalamnya.
        FORM: "Denah & Brosur Teknis", peringkat 1 dari 7 kandidat grounded, seed key 1c4b6ffd.
        FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
    -->
    <a href="#main-content" class="skip-link">Lewati ke konten utama</a>

    <x-site-navbar />

    <div class="pointer-events-none fixed inset-x-0 top-20 z-[60] mx-auto w-full max-w-3xl px-4" aria-live="polite">
        @if(session('success'))
            <div class="pointer-events-auto mb-3 flex items-start gap-3 border border-emerald-700/30 bg-paper-50 px-4 py-3 text-sm text-emerald-800 shadow-[0_16px_40px_-20px_rgba(23,26,21,.35)]">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7" />
                </svg>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if(session('status'))
            {{-- Kabar netral (mis. hasil rekonsiliasi yang belum berarti lunas):
                 bukan sukses, bukan error. Tanpa blok ini flash 'status' hilang
                 tanpa jejak. --}}
            <div class="pointer-events-auto mb-3 flex items-start gap-3 border border-ink-300 bg-paper-50 px-4 py-3 text-sm text-ink-700 shadow-[0_16px_40px_-20px_rgba(23,26,21,.35)]">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-blueprint-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16v-4m0-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="pointer-events-auto border border-dimension-500 bg-dimension-50 px-4 py-3 text-sm text-dimension-700" role="alert">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-dimension-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                    </svg>
                    <div>
                        <p class="font-semibold">Periksa kembali data Anda.</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- tabindex="-1" supaya skip link benar-benar memindahkan fokus keyboard;
         tanpa itu Safari/Firefox hanya menggeser scroll. --}}
    <main id="main-content" tabindex="-1">
        @yield('content')
    </main>

    <x-site-footer :popular-cities="$popularCities ?? collect()" />

    @stack('scripts')
</body>

</html>
