@extends('layouts.app')

@section('title', 'Daftar Akun — Santhosa')

@section('content')
    <section class="bg-ink-950 py-16 sm:py-24">
        <div class="site-container">
            <div class="bg-ink-900 mx-auto p-7 sm:p-9 border border-white/10 max-w-md">
                <p class="before:hidden section-eyebrow">Akun Santhosa</p>
                <h1 class="mt-3 font-display font-semibold text-white text-3xl tracking-[-0.025em]">Buat akun baru</h1>
                <p class="mt-3 text-ink-300 text-sm leading-6">Satu akun untuk memesan unit dan memantau status reservasi
                    Anda.</p>

                <form action="{{ route('register') }}" method="POST" class="space-y-4 mt-8" data-submit-loading>
                    @csrf

                    <div>
                        <label class="search-field @error('name') border-rose-400/70 @enderror">
                            <span class="search-field__label">Nama lengkap</span>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                autocomplete="name" placeholder="Nama sesuai identitas"
                                @error('name') aria-invalid="true" aria-describedby="name_error" @enderror
                                class="search-field__control">
                        </label>
                        @error('name')
                            <p id="name_error" class="mt-1.5 text-rose-300 text-xs leading-5">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="search-field @error('email') border-rose-400/70 @enderror">
                            <span class="search-field__label">Alamat email</span>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                autocomplete="email" placeholder="nama@email.com"
                                @error('email') aria-invalid="true" aria-describedby="email_error" @enderror
                                class="search-field__control">
                        </label>
                        @error('email')
                            <p id="email_error" class="mt-1.5 text-rose-300 text-xs leading-5">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="search-field @error('phone') border-rose-400/70 @enderror">
                            <span class="search-field__label">No. WhatsApp / HP</span>
                            <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required
                                autocomplete="tel" inputmode="tel" placeholder="081234567890"
                                @error('phone') aria-invalid="true" aria-describedby="phone_error" @enderror
                                class="search-field__control">
                        </label>
                        @error('phone')
                            <p id="phone_error" class="mt-1.5 text-rose-300 text-xs leading-5">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="search-field @error('password') border-rose-400/70 @enderror">
                            <span class="search-field__label">Password</span>
                            <input type="password" name="password" id="password" required autocomplete="new-password"
                                placeholder="Minimal 8 karakter"
                                @error('password') aria-invalid="true" aria-describedby="password_error" @enderror
                                class="search-field__control">
                        </label>
                        @error('password')
                            <p id="password_error" class="mt-1.5 text-rose-300 text-xs leading-5">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="search-field @error('password_confirmation') border-rose-400/70 @enderror">
                            <span class="search-field__label">Konfirmasi password</span>
                            <input type="password" name="password_confirmation" id="password_confirmation" required
                                autocomplete="new-password" placeholder="Ulangi password"
                                @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation_error" @enderror
                                class="search-field__control">
                        </label>
                        @error('password_confirmation')
                            <p id="password_confirmation_error" class="mt-1.5 text-rose-300 text-xs leading-5">
                                {{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full gold-button" data-loading-label="Memproses...">
                        <span>Daftar Sekarang</span>
                    </button>
                </form>

                <p class="mt-7 pt-6 border-white/10 border-t text-ink-400 text-xs">
                    Sudah memiliki akun?
                    <a href="{{ route('login') }}"
                        class="font-bold text-gold-400 hover:text-gold-200 transition-colors">Masuk di sini</a>
                </p>
            </div>
        </div>
    </section>
@endsection
