@extends('layouts.guest')

@section('title', 'Daftar — IDX Invest')

@php $wide = true; @endphp

@section('content')
<h1 class="text-lg font-extrabold mb-1">Buat Akun</h1>
<p class="text-sm text-slate-400 mb-6">Pilih paket langganan dan lengkapi data Anda.</p>

<form method="POST" action="{{ route('register') }}" class="space-y-5" x-data="{ plan: '{{ old('subscription_plan_id', $plans->first()?->id) }}' }">
    @csrf

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-2">Pilih Paket</label>
        @php
            // Written out as whole literal class names rather than interpolated
            // into the class string. Tailwind builds its stylesheet by scanning
            // these files for literal matches, so "sm:grid-cols-{{ $n }}" never
            // produced sm:grid-cols-3 and the plans stacked in one column. It
            // only looked fine while the layouts fell back to the Tailwind CDN,
            // which compiles against the live DOM instead.
            $planCols = match (min(3, max(1, $plans->count()))) {
                1 => 'sm:grid-cols-1',
                2 => 'sm:grid-cols-2',
                default => 'sm:grid-cols-3',
            };
        @endphp
        <div class="grid grid-cols-1 {{ $planCols }} gap-3">
            @forelse ($plans as $plan)
                <label class="relative block rounded-xl border-2 p-4 cursor-pointer transition"
                       :class="plan == '{{ $plan->id }}' ? 'border-primary bg-indigo-50' : 'border-slate-200 hover:border-slate-300'">
                    <input type="radio" name="subscription_plan_id" value="{{ $plan->id }}" x-model="plan" class="sr-only"
                           @checked(old('subscription_plan_id', $plans->first()?->id) == $plan->id)>
                    <div class="font-extrabold">{{ $plan->name }}</div>
                    <div class="text-xl font-extrabold text-primary mt-1">
                        Rp {{ number_format((float) $plan->price_per_month, 0, ',', '.') }}
                        <span class="text-xs font-semibold text-slate-400">/bulan</span>
                    </div>
                    @if ($plan->description)
                        <p class="text-xs text-slate-500 mt-2">{{ $plan->description }}</p>
                    @endif
                    @if (is_array($plan->features))
                        <ul class="text-xs text-slate-500 mt-2 space-y-1">
                            @foreach ($plan->features as $feature)
                                <li><i class="fa-solid fa-check text-emerald-500 mr-1"></i>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif
                </label>
            @empty
                <p class="text-sm text-red-600 col-span-full">Belum ada paket langganan tersedia. Hubungi admin.</p>
            @endforelse
        </div>
        @error('subscription_plan_id')<p class="text-xs text-red-600 mt-2 font-semibold">{{ $message }}</p>@enderror
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Nama Lengkap</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus
                   class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-base sm:text-sm sm:py-2 @error('name') border-red-400 @enderror">
            @error('name')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-base sm:text-sm sm:py-2 @error('email') border-red-400 @enderror">
            @error('email')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Password</label>
            <input type="password" name="password" required autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-base sm:text-sm sm:py-2 @error('password') border-red-400 @enderror">
            @error('password')<p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-base sm:text-sm sm:py-2">
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
        Setelah daftar, akun Anda berstatus <b>Menunggu Aktivasi</b> sampai admin mengonfirmasi pembayaran langganan.
    </div>

    <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2.5 text-sm hover:bg-indigo-700 transition">Daftar</button>
</form>

<p class="text-center text-sm text-slate-400 mt-6">
    Sudah punya akun? <a href="{{ route('login') }}" class="text-primary font-bold hover:underline">Masuk</a>
</p>
@endsection
