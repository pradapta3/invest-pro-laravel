@extends('layouts.app')

@section('title', 'Quant Scan — IDX Invest')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-xl font-extrabold"><x-icon name="brain" class="text-primary mr-2 w-4 h-4" :solid="true" />Quant Scan</h1>
        <p class="text-sm text-slate-400">Math Regression + Gemini 2.0</p>
    </div>
    <a href="{{ route('dashboard') }}" class="text-sm font-bold border border-slate-200 rounded-lg px-3 py-1.5 hover:bg-slate-50">Dashboard</a>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden" x-data="{ tab: 'magic' }">
    <div class="p-3 border-b border-slate-100 overflow-x-auto">
        <div class="flex gap-2 flex-nowrap">
            @foreach (['magic' => '🧙 Magic', 'bandar' => '🐳 Bandar', 'trend' => '🚀 Trend', 'pullback' => '🎯 Sniper', 'value' => '💎 Value'] as $key => $label)
                <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200'" class="rounded-full px-4 py-1.5 text-sm font-bold whitespace-nowrap transition">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div x-show="tab === 'magic'">@include('partials.screener-table', ['rows' => $magic, 'type' => 'magic'])</div>
    <div x-show="tab === 'bandar'" x-cloak>@include('partials.screener-table', ['rows' => $bandar, 'type' => 'bandar'])</div>
    <div x-show="tab === 'trend'" x-cloak>@include('partials.screener-table', ['rows' => $trend, 'type' => 'trend'])</div>
    <div x-show="tab === 'pullback'" x-cloak>@include('partials.screener-table', ['rows' => $pullback, 'type' => 'pullback'])</div>
    <div x-show="tab === 'value'" x-cloak>@include('partials.screener-table', ['rows' => $value, 'type' => 'value'])</div>
</div>
@endsection
