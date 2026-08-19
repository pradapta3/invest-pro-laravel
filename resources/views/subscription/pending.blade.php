@extends('layouts.guest')

@section('title', 'Menunggu Aktivasi — IDX Invest')

@section('content')
<div class="text-center">
    <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-500 flex items-center justify-center mx-auto mb-4">
        <x-icon name="hourglass-half" class="text-2xl w-4 h-4" :solid="true" />
    </div>

    @if ($subscription === null)
        <h1 class="text-lg font-extrabold mb-2">Belum Ada Langganan</h1>
        <p class="text-sm text-slate-500">Akun Anda belum memiliki paket langganan. Hubungi admin untuk mengaktifkan akses.</p>
    @else
        <h1 class="text-lg font-extrabold mb-2">Status: {{ $subscription->status->label() }}</h1>
        <p class="text-sm text-slate-500 mb-4">
            Paket <b>{{ $subscription->plan->name }}</b>
            (Rp {{ number_format((float) $subscription->plan->price_per_month, 0, ',', '.') }}/bulan)
            sedang menunggu aktivasi oleh admin. Anda akan bisa mengakses aplikasi setelah pembayaran dikonfirmasi.
        </p>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm font-bold text-slate-400 hover:text-slate-600">
            <x-icon name="right-from-bracket" class="mr-1 w-4 h-4" :solid="true" />Keluar
        </button>
    </form>
</div>
@endsection
