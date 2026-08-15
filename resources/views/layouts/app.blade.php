<!DOCTYPE html>
<html lang="id" class="h-full scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Santhosa — Apartemen Premium')</title>
    <meta name="description" content="@yield('meta_description', 'Temukan dan pesan apartemen premium di lokasi strategis dengan proses reservasi yang aman dan transparan.')">
    <meta name="theme-color" content="#07101f">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @stack('styles')
</head>

<body class="min-h-full bg-ink-950 font-sans text-ivory-100 antialiased">
    <a href="#main-content" class="skip-link">Lewati ke konten utama</a>

    <x-site-navbar />

    <div class="pointer-events-none fixed inset-x-0 top-20 z-[60] mx-auto w-full max-w-3xl px-4" aria-live="polite">
        @if(session('success'))
            <div class="pointer-events-auto mb-3 flex items-start gap-3 border border-emerald-400/30 bg-emerald-950/95 px-4 py-3 text-sm text-emerald-100 shadow-2xl shadow-black/30 backdrop-blur">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7" />
                </svg>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="pointer-events-auto border border-rose-400/30 bg-rose-950/95 px-4 py-3 text-sm text-rose-100 shadow-2xl shadow-black/30 backdrop-blur" role="alert">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                    </svg>
                    <div>
                        <p class="font-semibold">Periksa kembali data Anda.</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4 text-rose-200">
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
