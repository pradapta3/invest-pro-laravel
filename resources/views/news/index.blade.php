@extends('layouts.app')

@section('title', 'News — IDX Invest')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-xl font-extrabold"><x-icon name="newspaper" class="text-red-500 mr-2 w-4 h-4" :solid="true" />Market News</h1>
    <a href="{{ route('dashboard') }}" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><x-icon name="arrow-left" class="mr-2 w-4 h-4" :solid="true" />Dashboard</a>
</div>

@if (empty($articles))
    <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl text-slate-400">Gagal memuat berita. Coba lagi nanti.</div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($articles as $article)
            @php
                // Literal Tailwind class strings on purpose (not string-built),
                // so the JIT content scanner can actually find them.
                $badgeClass = match ($article['color']) {
                    'success' => 'text-emerald-600 bg-emerald-50',
                    'info' => 'text-sky-600 bg-sky-50',
                    default => 'text-primary bg-indigo-50',
                };
            @endphp
            <a href="{{ $article['link'] }}" target="_blank" rel="noopener noreferrer" class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:shadow-lg transition block">
                <img src="{{ $article['img'] }}" alt="" class="w-full h-40 object-cover bg-slate-100" loading="lazy">
                <div class="p-4">
                    <span class="text-[10px] font-bold uppercase rounded px-2 py-0.5 {{ $badgeClass }}">{{ $article['source'] }}</span>
                    <h3 class="font-bold text-sm mt-2 mb-1 line-clamp-2">{{ $article['title'] }}</h3>
                    <p class="text-xs text-slate-400 line-clamp-2 mb-2">{{ $article['desc'] }}</p>
                    <div class="text-[10px] text-slate-300">{{ \Illuminate\Support\Carbon::createFromTimestamp($article['date'])->diffForHumans() }}</div>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
