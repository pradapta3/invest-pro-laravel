@extends('layouts.guest')

@section('title', 'Reset Password — IDX Invest')

@section('content')
<h1 class="text-lg font-extrabold mb-1">Reset Password</h1>
<p class="text-sm text-slate-400 mb-6">Buat password baru untuk akun Anda.</p>

<form method="POST" action="{{ route('password.store') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus autocomplete="username"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm @error('email') border-red-400 @enderror">
        @error('email')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Password Baru</label>
        <input type="password" name="password" required autocomplete="new-password"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm @error('password') border-red-400 @enderror">
        @error('password')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Konfirmasi Password Baru</label>
        <input type="password" name="password_confirmation" required autocomplete="new-password"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2.5 text-sm hover:bg-indigo-700 transition">Reset Password</button>
</form>
@endsection
