{{-- Title block lembar gambar: sel proyek di kiri, indeks lembar di tengah,
     sel pengguna + aksi di kanan. Perilaku (toggle, aria, id) identik dengan
     sebelumnya — app.js bergantung pada atribut data-* di bawah. --}}
<header class="sticky top-0 z-50 border-b border-ink-300 bg-paper-50">
    <div class="site-container flex h-[72px] items-stretch justify-between gap-5">
        <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-2.5 self-center" aria-label="Santhosa, kembali ke beranda">
            <svg class="h-5 w-5 shrink-0 text-dimension-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <circle cx="10" cy="10" r="6.5" />
                <path stroke-linecap="round" d="M10 0.5v4M10 15.5v4M0.5 10h4M15.5 10h4" />
                <circle cx="10" cy="10" r="1.2" fill="currentColor" stroke="none" />
            </svg>
            <span class="font-xwide text-lg font-extrabold uppercase leading-none tracking-[0.02em] text-ink-900">
                Santhosa
            </span>
        </a>

        <nav class="hidden items-center gap-7 lg:flex" aria-label="Navigasi utama">
            <a href="{{ route('home') }}" class="flex min-h-11 items-center border-b-2 font-annotation text-xs font-bold uppercase tracking-[0.08em] transition-colors {{ request()->routeIs('home') ? 'border-dimension-600 text-ink-900' : 'border-transparent text-ink-500 hover:text-ink-900' }}">Beranda</a>
            <a href="{{ route('apartments.index') }}" class="flex min-h-11 items-center border-b-2 font-annotation text-xs font-bold uppercase tracking-[0.08em] transition-colors {{ request()->routeIs('apartments.*') ? 'border-dimension-600 text-ink-900' : 'border-transparent text-ink-500 hover:text-ink-900' }}">Unit</a>
            <a href="{{ route('home') }}#facilities" class="flex min-h-11 items-center border-b-2 border-transparent font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-500 transition-colors hover:text-ink-900">Fasilitas</a>
        </nav>

        <div class="hidden items-center gap-5 lg:flex">
            @auth
                @if(Auth::user()->isAdmin())
                    <a href="/admin" class="flex min-h-11 items-center font-annotation text-xs font-bold uppercase tracking-[0.08em] text-blueprint-700 transition-colors hover:text-blueprint-500">Admin</a>
                @endif
                <a href="{{ route('bookings.index') }}" class="flex min-h-11 items-center font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-600 transition-colors hover:text-ink-900">Booking saya</a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex min-h-11 items-center gap-2 font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-500 transition-colors hover:text-ink-900">
                        <span class="flex h-8 w-8 items-center justify-center border border-ink-300 bg-paper-200 font-annotation text-xs font-bold text-blueprint-700">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        Keluar
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="flex min-h-11 items-center font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-600 transition-colors hover:text-ink-900">Masuk</a>
            @endauth

            <a href="{{ route('apartments.index') }}" class="btn-primary min-w-40">Pesan sekarang</a>
        </div>

        <button
            type="button"
            class="my-auto flex h-11 w-11 items-center justify-center border border-ink-300 text-ink-800 transition-colors hover:border-blueprint-500 hover:text-blueprint-700 lg:hidden"
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

    <div id="mobile-navigation" class="hidden border-t border-ink-300 bg-paper-50 lg:hidden" data-mobile-menu>
        <nav class="site-container flex max-h-[calc(100svh-72px)] flex-col overflow-y-auto py-5" aria-label="Navigasi mobile">
            <a href="{{ route('home') }}" class="flex min-h-12 items-center border-b border-ink-200 font-annotation text-sm font-bold uppercase tracking-[0.06em] text-ink-900">Beranda</a>
            <a href="{{ route('apartments.index') }}" class="flex min-h-12 items-center border-b border-ink-200 font-annotation text-sm font-bold uppercase tracking-[0.06em] text-ink-600">Unit</a>
            <a href="{{ route('home') }}#facilities" class="flex min-h-12 items-center border-b border-ink-200 font-annotation text-sm font-bold uppercase tracking-[0.06em] text-ink-600">Fasilitas</a>

            @auth
                <a href="{{ route('bookings.index') }}" class="flex min-h-12 items-center border-b border-ink-200 font-annotation text-sm font-bold uppercase tracking-[0.06em] text-ink-600">Booking saya</a>
                @if(Auth::user()->isAdmin())
                    <a href="/admin" class="flex min-h-12 items-center border-b border-ink-200 font-annotation text-sm font-bold uppercase tracking-[0.06em] text-blueprint-700">Panel admin</a>
                @endif
                <form action="{{ route('logout') }}" method="POST" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-secondary w-full">Keluar</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn-secondary mt-4 w-full">Masuk</a>
                <a href="{{ route('register') }}" class="mt-2 flex min-h-11 items-center justify-center font-annotation text-sm font-bold uppercase tracking-[0.06em] text-ink-600">Buat akun</a>
            @endauth

            <a href="{{ route('apartments.index') }}" class="btn-primary mt-3 w-full">Pesan sekarang</a>
        </nav>
    </div>
</header>
