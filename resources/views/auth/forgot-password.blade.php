@extends('layouts.guest')

@section('title', 'Lupa Password — IDX Invest')

@section('content')
<h1 class="text-lg font-extrabold mb-1">Lupa Password</h1>
<p class="text-sm text-slate-400 mb-6">Masukkan email Anda, kami kirim tautan untuk reset password.</p>

<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm @error('email') border-red-400 @enderror">
        @error('email')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2.5 text-sm hover:bg-indigo-700 transition">Kirim Tautan Reset</button>
</form>

<p class="text-center text-sm text-slate-400 mt-6">
    <a href="{{ route('login') }}" class="text-primary font-bold hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Kembali ke login</a>
</p>
@endsection
