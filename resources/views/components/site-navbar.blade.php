<header class="sticky top-0 z-50 border-b border-white/10 bg-ink-900/95 backdrop-blur-md">
    <div class="site-container flex h-[72px] items-center justify-between gap-5">
        <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-2 font-display text-lg font-semibold tracking-[-0.02em] text-white" aria-label="Santhosa, kembali ke beranda">
            <span class="h-2 w-2 rounded-full bg-gold-400 shadow-[0_0_16px_rgba(228,184,47,.7)]" aria-hidden="true"></span>
            <span>Santhosa</span>
        </a>

        <nav class="hidden items-center gap-8 lg:flex" aria-label="Navigasi utama">
            <a href="{{ route('home') }}" class="min-h-11 content-center text-xs font-semibold transition-colors {{ request()->routeIs('home') ? 'text-white' : 'text-ink-300 hover:text-white' }}">Beranda</a>
            <a href="{{ route('apartments.index') }}" class="min-h-11 content-center text-xs font-semibold transition-colors {{ request()->routeIs('apartments.*') ? 'text-white' : 'text-ink-300 hover:text-white' }}">Unit</a>
            <a href="{{ route('home') }}#facilities" class="min-h-11 content-center text-xs font-semibold text-ink-300 transition-colors hover:text-white">Fasilitas</a>
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            @auth
                @if(Auth::user()->isAdmin())
                    <a href="/admin" class="min-h-11 content-center text-xs font-semibold text-gold-300 transition-colors hover:text-gold-200">Admin</a>
                @endif
                <a href="{{ route('bookings.index') }}" class="min-h-11 content-center text-xs font-semibold text-ink-200 transition-colors hover:text-white">Booking saya</a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex min-h-11 items-center gap-2 text-xs font-semibold text-ink-300 transition-colors hover:text-white">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-ink-800 text-[11px] font-bold text-gold-300">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        Keluar
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="min-h-11 content-center text-xs font-semibold text-ink-200 transition-colors hover:text-white">Masuk</a>
            @endauth

            <a href="{{ route('apartments.index') }}" class="gold-button min-w-40">Pesan sekarang</a>
        </div>

        <button
            type="button"
            class="flex h-11 w-11 items-center justify-center border border-white/10 text-white transition-colors hover:border-gold-400/50 hover:text-gold-300 lg:hidden"
            aria-label="Buka menu navigasi"
            aria-controls="mobile-navigation"
            aria-expanded="false"
            data-mobile-menu-toggle
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" />
            </svg>
        </button>
    </div>

    <div id="mobile-navigation" class="hidden border-t border-white/10 bg-ink-900 lg:hidden" data-mobile-menu>
        <nav class="site-container flex max-h-[calc(100svh-72px)] flex-col overflow-y-auto py-5" aria-label="Navigasi mobile">
            <a href="{{ route('home') }}" class="flex min-h-12 items-center border-b border-white/8 text-sm font-semibold text-white">Beranda</a>
            <a href="{{ route('apartments.index') }}" class="flex min-h-12 items-center border-b border-white/8 text-sm font-semibold text-ink-200">Unit</a>
            <a href="{{ route('home') }}#facilities" class="flex min-h-12 items-center border-b border-white/8 text-sm font-semibold text-ink-200">Fasilitas</a>

            @auth
                <a href="{{ route('bookings.index') }}" class="flex min-h-12 items-center border-b border-white/8 text-sm font-semibold text-ink-200">Booking saya</a>
                @if(Auth::user()->isAdmin())
                    <a href="/admin" class="flex min-h-12 items-center border-b border-white/8 text-sm font-semibold text-gold-300">Panel admin</a>
                @endif
                <form action="{{ route('logout') }}" method="POST" class="mt-4">
                    @csrf
                    <button type="submit" class="flex min-h-11 w-full items-center justify-center border border-white/15 text-sm font-semibold text-white">Keluar</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mt-4 flex min-h-11 items-center justify-center border border-white/15 text-sm font-semibold text-white">Masuk</a>
                <a href="{{ route('register') }}" class="mt-2 flex min-h-11 items-center justify-center text-sm font-semibold text-ink-200">Buat akun</a>
            @endauth

            <a href="{{ route('apartments.index') }}" class="gold-button mt-3 w-full">Pesan sekarang</a>
        </nav>
    </div>
</header>
