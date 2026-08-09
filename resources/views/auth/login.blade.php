@extends('layouts.guest')

@section('title', 'Login — IDX Invest')

@section('content')
<h1 class="text-lg font-extrabold mb-1">Masuk</h1>
<p class="text-sm text-slate-400 mb-6">Selamat datang kembali.</p>

<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm @error('email') border-red-400 @enderror">
        @error('email')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Password</label>
        <div class="relative" x-data="{ show: false }">
            <input :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 pr-10 text-sm @error('password') border-red-400 @enderror">
            <button type="button" @click="show = !show" tabindex="-1"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
                    :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                <i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
            </button>
        </div>
        @error('password')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2 text-slate-500">
            <input type="checkbox" name="remember" class="rounded">
            Ingat saya
        </label>
        <a href="{{ route('password.request') }}" class="text-primary font-bold hover:underline">Lupa password?</a>
    </div>

    @if (config('services.recaptcha.site_key'))
        <div>
            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
            @error('g-recaptcha-response')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
        </div>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif

    <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2.5 text-sm hover:bg-indigo-700 transition">Masuk</button>
</form>

<p class="text-center text-sm text-slate-400 mt-6">
    Belum punya akun? <a href="{{ route('register') }}" class="text-primary font-bold hover:underline">Daftar</a>
</p>
@endsection
