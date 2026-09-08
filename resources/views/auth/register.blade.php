@extends('layouts.app')

@section('title', 'Daftar Akun — Santhosa')

@section('content')
    <section class="graph-grid bg-paper-100 py-16 sm:py-24">
        <div class="site-container">
            <div class="mx-auto max-w-md border border-ink-300 bg-paper-50 shadow-[0_16px_40px_-24px_rgba(23,26,21,.3)]">
                <div class="flex items-center justify-between border-b border-ink-300 bg-paper-200 px-6 py-3">
                    <h1 class="font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-800">Formulir pendaftaran</h1>
                    <span class="annotation uppercase">Akun baru</span>
                </div>

                <div class="p-6 sm:p-8">
                    <p class="text-2xl font-bold tracking-[-0.015em] text-ink-900">Buat akun baru</p>
                    <p class="mt-2.5 text-sm leading-6 text-ink-600">Satu akun untuk memesan unit dan memantau status reservasi Anda.</p>

                    <form action="{{ route('register') }}" method="POST" class="mt-7 space-y-4" data-submit-loading>
                        @csrf

                        <div>
                            <label class="field @error('name') field--error @enderror">
                                <span class="field__label">Nama lengkap</span>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    autocomplete="name" placeholder="Nama sesuai identitas"
                                    @error('name') aria-invalid="true" aria-describedby="name_error" @enderror
                                    class="field__control">
                            </label>
                            @error('name')
                                <p id="name_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="field @error('email') field--error @enderror">
                                <span class="field__label">Alamat email</span>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                    autocomplete="email" placeholder="nama@email.com"
                                    @error('email') aria-invalid="true" aria-describedby="email_error" @enderror
                                    class="field__control">
                            </label>
                            @error('email')
                                <p id="email_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="field @error('phone') field--error @enderror">
                                <span class="field__label">No. WhatsApp / HP</span>
                                <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required
                                    autocomplete="tel" inputmode="tel" placeholder="081234567890"
                                    @error('phone') aria-invalid="true" aria-describedby="phone_error" @enderror
                                    class="field__control">
                            </label>
                            @error('phone')
                                <p id="phone_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="field @error('password') field--error @enderror">
                                <span class="field__label">Password</span>
                                <input type="password" name="password" id="password" required autocomplete="new-password"
                                    placeholder="Minimal 8 karakter"
                                    @error('password') aria-invalid="true" aria-describedby="password_error" @enderror
                                    class="field__control">
                            </label>
                            @error('password')
                                <p id="password_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="field @error('password_confirmation') field--error @enderror">
                                <span class="field__label">Konfirmasi password</span>
                                <input type="password" name="password_confirmation" id="password_confirmation" required
                                    autocomplete="new-password" placeholder="Ulangi password"
                                    @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation_error" @enderror
                                    class="field__control">
                            </label>
                            @error('password_confirmation')
                                <p id="password_confirmation_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="btn-primary w-full" data-loading-label="Memproses...">
                            <span>Daftar Sekarang</span>
                        </button>
                    </form>

                    <p class="mt-7 border-t border-ink-200 pt-6 text-sm text-ink-600">
                        Sudah memiliki akun?
                        <a href="{{ route('login') }}" class="font-bold text-blueprint-700 transition-colors hover:text-blueprint-500">Masuk di sini</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
