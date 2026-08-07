<!DOCTYPE html>
<html lang="id" class="h-full scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ApartStay - Sewa Apartemen Mewah & Nyaman')</title>
    <meta name="description" content="Platform sewa apartemen harian & bulanan terpercaya dengan fasilitas bintang lima dan kemudahan pembayaran online.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Compiled Tailwind CSS (Vite) — see resources/css/app.css -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
    @stack('styles')
</head>

<body class="bg-slate-900 text-slate-100 font-sans antialiased flex flex-col min-h-full">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 bg-slate-900/90 backdrop-blur-md border-b border-slate-800 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">

                <!-- Logo -->
                <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-teal-400 flex items-center justify-center shadow-lg shadow-brand-500/20 group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <span class="text-2xl font-extrabold tracking-tight text-white">Apart<span class="text-brand-500">Stay</span></span>
                </a>

                <!-- Desktop Nav Links -->
                <div class="hidden md:flex items-center space-x-8 text-sm font-medium">
                    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'text-brand-400 font-semibold' : 'text-slate-300 hover:text-white' }} transition-colors">Beranda</a>
                    <a href="{{ route('apartments.index') }}" class="{{ request()->routeIs('apartments.*') ? 'text-brand-400 font-semibold' : 'text-slate-300 hover:text-white' }} transition-colors">Daftar Apartemen</a>
                    @auth
                    <a href="{{ route('bookings.index') }}" class="{{ request()->routeIs('bookings.*') ? 'text-brand-400 font-semibold' : 'text-slate-300 hover:text-white' }} transition-colors">Riwayat Booking</a>
                    @endauth
                </div>

                <!-- Auth Buttons -->
                <div class="flex items-center gap-4">
                    @auth
                    <div class="relative flex items-center gap-3">
                        @if(Auth::user()->isAdmin())
                        <a href="/admin" class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-amber-400 bg-amber-500/10 border border-amber-500/30 rounded-lg hover:bg-amber-500/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Admin Panel
                        </a>
                        @endif

                        <div class="flex items-center gap-3 pl-3 border-l border-slate-800">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-teal-500 flex items-center justify-center font-bold text-white shadow-md text-sm">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <span class="hidden md:inline-block text-sm font-medium text-slate-200">{{ Auth::user()->name }}</span>
                        </div>

                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" title="Logout" class="p-2 text-slate-400 hover:text-rose-400 hover:bg-slate-800 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>
                    @else
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-300 hover:text-white transition-colors px-3 py-2">Masuk</a>
                    <a href="{{ route('register') }}" class="px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-brand-500 to-brand-600 rounded-xl hover:shadow-lg hover:shadow-brand-500/25 hover:from-brand-600 hover:to-brand-700 transition-all">Daftar Sekarang</a>
                    @endauth
                </div>

            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-3 rounded-xl flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 px-4 py-3 rounded-xl">
            <ul class="list-disc list-inside space-y-1 text-sm">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <!-- Main Content -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-800 text-slate-400 py-12 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center text-white font-bold text-lg">A</div>
                        <span class="text-xl font-bold text-white">ApartStay</span>
                    </div>
                    <p class="text-sm text-slate-400 leading-relaxed">Platform pemesanan apartemen paling praktis, aman, dan transparan di Indonesia. Pengalaman menginap berbintang lima.</p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Navigasi</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('home') }}" class="hover:text-brand-400 transition-colors">Beranda</a></li>
                        <li><a href="{{ route('apartments.index') }}" class="hover:text-brand-400 transition-colors">Semua Apartemen</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-brand-400 transition-colors">Area Admin / Login</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Kota Populer</h4>
                    <ul class="space-y-2 text-sm">
                        @forelse($popularCities as $city)
                        <li><a href="{{ route('apartments.index', ['city' => $city]) }}" class="hover:text-brand-400 transition-colors">{{ $city }}</a></li>
                        @empty
                        <li class="text-slate-500">Belum ada kota tersedia</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Metode Pembayaran</h4>
                    <p class="text-xs text-slate-400 mb-3">Terintegrasi dengan Midtrans Payment Gateway (QRIS, GoPay, BCA, Mandiri, Credit Card).</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-1 text-[10px] font-bold bg-slate-800 text-slate-300 rounded border border-slate-700">QRIS</span>
                        <span class="px-2.5 py-1 text-[10px] font-bold bg-slate-800 text-slate-300 rounded border border-slate-700">GoPay</span>
                        <span class="px-2.5 py-1 text-[10px] font-bold bg-slate-800 text-slate-300 rounded border border-slate-700">Bank Transfer</span>
                        <span class="px-2.5 py-1 text-[10px] font-bold bg-slate-800 text-slate-300 rounded border border-slate-700">Credit Card</span>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-xs">
                <p>&copy; {{ date('Y') }} ApartStay Inc. Hak Cipta Dilindungi Undang-Undang.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>

</html>