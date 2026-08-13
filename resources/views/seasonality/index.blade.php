@extends('layouts.app')

@section('title', 'Seasonal Compass — IDX Invest')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-xl font-extrabold"><x-icon name="calendar-check" class="text-primary mr-2 w-4 h-4" />Seasonal Compass</h1>
    <a href="{{ route('dashboard') }}" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><x-icon name="arrow-left" class="mr-2 w-4 h-4" :solid="true" />Dashboard</a>
</div>

<form method="GET" class="mb-4 flex gap-2 max-w-sm">
    <input type="text" name="ticker" value="{{ $ticker }}" placeholder="Kode saham, cth: BBCA" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold uppercase">
    <button type="submit" class="rounded-lg bg-primary text-white font-bold px-4 py-2 text-sm hover:bg-indigo-700 transition">Cari</button>
</form>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
        <h2 class="font-bold">{{ $ticker }} <span class="text-sm font-normal text-slate-400">{{ $ref?->nama_perusahaan }}</span></h2>
        @if (!empty($monthlyByYear))
            <span class="text-xs font-bold bg-white border border-slate-200 rounded-full px-3 py-1">
                Best: <b class="text-emerald-600">{{ \Carbon\Carbon::create()->month($seasonality['best']['month'])->translatedFormat('M') }}</b>
                · Worst: <b class="text-red-600">{{ \Carbon\Carbon::create()->month($seasonality['worst']['month'])->translatedFormat('M') }}</b>
            </span>
        @endif
    </div>
    <div class="p-4">
        @include('partials.seasonality-table')
    </div>
</div>
@endsection
