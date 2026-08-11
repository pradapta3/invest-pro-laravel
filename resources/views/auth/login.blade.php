@extends('layouts.guest')

@section('title', 'Login — IDX Invest')

@section('content')
<h1 class="text-lg font-extrabold mb-1">Masuk</h1>
<p class="text-sm text-slate-400 mb-6">Selamat datang kembali.</p>

<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf

    <div>
        <label for="email" class="block text-xs font-bold text-slate-500 mb-1">Email</label>
        {{-- text-base below sm: iOS Safari zooms the page in when a focused input
             is under 16px, and never zooms back out. --}}
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               inputmode="email"
               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-base sm:text-sm sm:py-2 @error('email') border-red-400 @enderror">
        @error('email')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="password" class="block text-xs font-bold text-slate-500 mb-1">Password</label>
        <div class="relative">
            {{-- pr-11 keeps the typed value clear of the toggle, which is 44px
                 wide so it is a comfortable touch target. --}}
            <input id="password" name="password" required autocomplete="current-password"
                   type="password"
                   class="w-full rounded-lg border border-slate-200 pl-3 pr-11 py-2.5 text-base sm:text-sm sm:py-2 @error('password') border-red-400 @enderror">
            {{-- Neither the behaviour nor the icon depends on a CDN. The first
                 version of this used Alpine for the toggle and a Font Awesome
                 glyph for the icon, which meant that on a connection where
                 those are blocked the button rendered as a blank 44px square
                 that did nothing. Inline script, inline SVG. --}}
            <button type="button" id="password-toggle"
                    aria-label="Tampilkan password" aria-pressed="false"
                    class="absolute inset-y-0 right-0 w-11 flex items-center justify-center text-slate-400 hover:text-slate-600 transition">
                <svg id="password-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="password-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 hidden">
                    <path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M6.61 6.61A18.4 18.4 0 0 0 2 11s3.5 7 10 7a9 9 0 0 0 5.39-1.61"/>
                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/>
                </svg>
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

    <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2.5 text-sm hover:bg-indigo-700 transition">Masuk</button>
</form>

<p class="text-center text-sm text-slate-400 mt-6">
    Belum punya akun? <a href="{{ route('register') }}" class="text-primary font-bold hover:underline">Daftar</a>
</p>

<script>
(function () {
    var input = document.getElementById('password');
    var btn = document.getElementById('password-toggle');
    var eye = document.getElementById('password-eye');
    var eyeOff = document.getElementById('password-eye-off');
    if (!input || !btn) return;

    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        if (eye) { eye.classList.toggle('hidden', show); }
        if (eyeOff) { eyeOff.classList.toggle('hidden', !show); }
    });
})();
</script>
@endsection
