@extends('layouts.app')

@section('title', 'Masuk — Santhosa')

@section('content')
    <section class="bg-ink-950 py-16 sm:py-24">
        <div class="site-container">
            <div class="mx-auto max-w-md border border-white/10 bg-ink-900 p-7 sm:p-9">
                <p class="section-eyebrow before:hidden">Akun Santhosa</p>
                <h1 class="mt-3 font-display text-3xl font-semibold tracking-[-0.025em] text-white">Selamat datang kembali</h1>
                <p class="mt-3 text-sm leading-6 text-ink-300">Masuk untuk melanjutkan reservasi dan melihat riwayat booking Anda.</p>

                <form action="{{ route('login') }}" method="POST" class="mt-8 space-y-4" data-submit-loading>
                    @csrf

                    <div>
                        <label class="search-field @error('email') border-rose-400/70 @enderror">
                            <span class="search-field__label">Alamat email</span>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email"
                                placeholder="nama@email.com"
                                @error('email') aria-invalid="true" aria-describedby="email_error" @enderror
                                class="search-field__control">
                        </label>
                        @error('email')
                            <p id="email_error" class="mt-1.5 text-xs leading-5 text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="search-field @error('password') border-rose-400/70 @enderror">
                            <span class="search-field__label">Password</span>
                            <input type="password" name="password" id="password" required autocomplete="current-password"
                                placeholder="••••••••"
                                @error('password') aria-invalid="true" aria-describedby="password_error" @enderror
                                class="search-field__control">
                        </label>
                        @error('password')
                            <p id="password_error" class="mt-1.5 text-xs leading-5 text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-xs text-ink-300">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 shrink-0 accent-gold-400">
                        <span>Ingat saya di perangkat ini</span>
                    </label>

                    <button type="submit" class="gold-button w-full" data-loading-label="Memproses...">
                        <span>Masuk</span>
                    </button>
                </form>

                <p class="mt-7 border-t border-white/10 pt-6 text-xs text-ink-400">
                    Belum memiliki akun?
                    <a href="{{ route('register') }}" class="font-bold text-gold-400 transition-colors hover:text-gold-200">Daftar sekarang</a>
                </p>
            </div>
        </div>
    </section>
@endsection
