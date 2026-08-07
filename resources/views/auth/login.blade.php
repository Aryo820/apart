@extends('layouts.app')

@section('title', 'Masuk Akun - ApartStay')

@section('content')
<div class="min-h-[70vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-slate-800/90 border border-slate-700/90 rounded-3xl p-8 shadow-2xl backdrop-blur-xl">
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-500 to-teal-400 flex items-center justify-center text-white font-bold text-xl mx-auto mb-3 shadow-lg shadow-brand-500/20">
                A
            </div>
            <h2 class="text-2xl font-extrabold text-white">Selamat Datang Kembali</h2>
            <p class="text-xs text-slate-400 mt-1">Masuk ke akun Anda untuk mengelola booking apartemen.</p>
        </div>

        <form action="{{ route('login') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="nama@email.com" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded bg-slate-900 border-slate-700 text-brand-500 focus:ring-0">
                    <span>Ingat saya</span>
                </label>
            </div>

            <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-brand-500/25 transition-all">
                Masuk
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-700/80 text-center text-xs text-slate-400">
            <span>Belum memiliki akun?</span>
            <a href="{{ route('register') }}" class="text-brand-400 font-bold hover:underline ml-1">Daftar Akun Baru</a>
        </div>
    </div>
</div>
@endsection
