@extends('layouts.app')

@section('title', 'Daftar Akun Baru - ApartStay')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-slate-800/90 border border-slate-700/90 rounded-3xl p-8 shadow-2xl backdrop-blur-xl">
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-500 to-teal-400 flex items-center justify-center text-white font-bold text-xl mx-auto mb-3 shadow-lg shadow-brand-500/20">
                A
            </div>
            <h2 class="text-2xl font-extrabold text-white">Buat Akun Baru</h2>
            <p class="text-xs text-slate-400 mt-1">Dapatkan akses reservasi apartemen mewah tercepat.</p>
        </div>

        <form action="{{ route('register') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Nama Anda" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="nama@email.com" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">No. WhatsApp / HP</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="081234567890" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">Password</label>
                <input type="password" name="password" required placeholder="Minimal 8 karakter" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" required placeholder="Ulangi password" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-brand-500/25 transition-all">
                Daftar Sekarang
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-700/80 text-center text-xs text-slate-400">
            <span>Sudah memiliki akun?</span>
            <a href="{{ route('login') }}" class="text-brand-400 font-bold hover:underline ml-1">Masuk Di Sini</a>
        </div>
    </div>
</div>
@endsection
