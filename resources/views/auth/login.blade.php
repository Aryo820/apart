@extends('layouts.app')

@section('title', 'Masuk — Santhosa')

@section('content')
    <section class="graph-grid bg-paper-100 py-16 sm:py-24">
        <div class="site-container">
            <div class="mx-auto max-w-md border border-ink-300 bg-paper-50 shadow-[0_16px_40px_-24px_rgba(23,26,21,.3)]">
                <div class="flex items-center justify-between border-b border-ink-300 bg-paper-200 px-6 py-3">
                    <h1 class="font-annotation text-xs font-bold uppercase tracking-[0.08em] text-ink-800">Formulir masuk</h1>
                    <span class="annotation uppercase">Akun tamu</span>
                </div>

                <div class="p-6 sm:p-8">
                    <p class="text-2xl font-bold tracking-[-0.015em] text-ink-900">Selamat datang kembali</p>
                    <p class="mt-2.5 text-sm leading-6 text-ink-600">Masuk untuk melanjutkan reservasi dan melihat riwayat booking Anda.</p>

                    <form action="{{ route('login') }}" method="POST" class="mt-7 space-y-4" data-submit-loading>
                        @csrf

                        <div>
                            <label class="field @error('email') field--error @enderror">
                                <span class="field__label">Alamat email</span>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email"
                                    placeholder="nama@email.com"
                                    @error('email') aria-invalid="true" aria-describedby="email_error" @enderror
                                    class="field__control">
                            </label>
                            @error('email')
                                <p id="email_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="field @error('password') field--error @enderror">
                                <span class="field__label">Password</span>
                                <input type="password" name="password" id="password" required autocomplete="current-password"
                                    placeholder="••••••••"
                                    @error('password') aria-invalid="true" aria-describedby="password_error" @enderror
                                    class="field__control">
                            </label>
                            @error('password')
                                <p id="password_error" class="mt-1.5 text-xs leading-5 text-dimension-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-ink-600">
                            <input type="checkbox" name="remember" value="1" class="h-4 w-4 shrink-0">
                            <span>Ingat saya di perangkat ini</span>
                        </label>

                        <button type="submit" class="btn-primary w-full" data-loading-label="Memproses...">
                            <span>Masuk</span>
                        </button>
                    </form>

                    <p class="mt-7 border-t border-ink-200 pt-6 text-sm text-ink-600">
                        Belum memiliki akun?
                        <a href="{{ route('register') }}" class="font-bold text-blueprint-700 transition-colors hover:text-blueprint-500">Daftar sekarang</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
