@extends('layouts.app')

@section('title', 'Ghost Pattern — IDX Invest')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-xl font-extrabold"><x-icon name="ghost" class="text-primary mr-2 w-4 h-4" :solid="true" />Ghost Hunter Pattern</h1>
    <a href="{{ route('dashboard') }}" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><x-icon name="arrow-left" class="mr-2 w-4 h-4" :solid="true" />Dashboard</a>
</div>

<form method="GET" class="mb-4 flex gap-2 max-w-sm">
    <input type="text" name="ticker" value="{{ $ticker }}" placeholder="Kode saham, cth: BBCA" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold uppercase">
    <button type="submit" class="rounded-lg bg-primary text-white font-bold px-4 py-2 text-sm hover:bg-indigo-700 transition">Cari Pola</button>
</form>

@if (! $target)
    <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl text-slate-400">Data historis {{ $ticker }} tidak ditemukan.</div>
@elseif ($matches->isEmpty())
    <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl">
        <x-icon name="ghost" class="text-slate-200 mb-4 w-4 h-4" :solid="true" />
        <p class="text-slate-400">Tidak ditemukan pola yang mirip (korelasi &gt; 60%) untuk {{ $ticker }}.</p>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($matches as $match)
            @php $t = str_replace('.JK', '', $match['price']->ticker); @endphp
            <div class="bg-white border border-slate-200 rounded-2xl p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h4 class="font-extrabold">{{ $t }}</h4>
                        <div class="text-xs text-slate-400 truncate max-w-[9rem]">{{ $match['price']->stockRef->nama_perusahaan ?? '' }}</div>
                    </div>
                    <span class="text-xs font-extrabold bg-indigo-50 text-primary rounded-full px-2.5 py-1">{{ $match['similarity'] }}%</span>
                </div>
                <x-sparkline :history="$match['price']->history_json ?? []" :width="150" :height="45" color="#4f46e5" />
                <div class="font-bold mt-2">Rp {{ number_format($match['price']->close_price) }}</div>
                <a href="{{ route('stocks.show', $t) }}" class="mt-3 block text-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-700 hover:text-white transition text-sm font-bold py-2">Lihat Detail</a>
            </div>
        @endforeach
    </div>
@endif
@endsection
