@extends('layouts.app')

@section('title', str_replace('.JK', '', $ref->ticker).' — Analisa Lengkap')

@section('content')
@php
    $cleanTicker = str_replace('.JK', '', $ref->ticker);
    $close = (float) $price->close_price;
    $open = (float) $price->open_price;
    $change = $close - $open;
    $changePct = $open > 0 ? ($change / $open) * 100 : 0;
    $verdict = $score->verdict();
    $verdictColor = match ($verdict) {
        'STRONG BUY' => 'text-emerald-600',
        'BUY' => 'text-primary',
        'NEUTRAL' => 'text-amber-600',
        default => 'text-red-600',
    };
    $flowStat = $price->moneyFlow();
    $trendUp = $price->isAboveMa20();
@endphp

<div class="bg-white border border-slate-200 rounded-2xl mb-4 p-4 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('dashboard') }}" class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center hover:bg-slate-200"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-extrabold">{{ $cleanTicker }} <span class="text-base font-normal text-slate-400">{{ $ref->nama_perusahaan }}</span></h1>
            <div class="flex items-center gap-2 mt-1">
                <span class="text-xs bg-slate-100 border border-slate-200 rounded-full px-2 py-0.5">{{ $ref->sector ?? '-' }}</span>
                <span class="text-xs rounded-full px-2 py-0.5 {{ match ($flowStat) {
                    'AKUM' => 'bg-emerald-100 text-emerald-700',
                    'DIST' => 'bg-red-100 text-red-700',
                    default => 'bg-slate-100 text-slate-400',
                } }}"
                      title="{{ $flowStat === null ? 'VWAP belum tersedia' : 'Posisi harga terhadap VWAP hari ini' }}">{{ $flowStat ?? 'FLOW -' }}</span>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-4">
        <div class="text-right">
            <div class="text-2xl font-extrabold {{ $change >= 0 ? 'text-emerald-600' : 'text-red-600' }}">Rp {{ number_format($close) }}</div>
            <div class="text-xs font-bold {{ $change >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $change >= 0 ? '+' : '' }}{{ number_format($change) }} ({{ number_format($changePct, 2) }}%)</div>
        </div>
        <div class="w-12 h-12 rounded-full flex items-center justify-center font-extrabold text-lg {{ $verdictColor }} border-4 border-slate-100">{{ $score->total() }}</div>
        <button onclick="openBuyModal('{{ $cleanTicker }}', {{ $close }})" class="rounded-lg bg-primary text-white font-bold px-4 py-2 hover:bg-indigo-700 transition text-sm">BUY</button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden" style="height: 400px;">
            <div id="tv_chart" class="w-full h-full"></div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex justify-between items-center text-xs font-bold uppercase text-slate-500">
                <span><i class="fa-regular fa-calendar-check mr-2"></i>Seasonality (5 Years)</span>
                <span class="normal-case font-medium">Best: <b class="text-emerald-600">{{ \Carbon\Carbon::create()->month($seasonality['best']['month'])->translatedFormat('M') }}</b> · Worst: <b class="text-red-600">{{ \Carbon\Carbon::create()->month($seasonality['worst']['month'])->translatedFormat('M') }}</b></span>
            </div>
            <div class="p-4">
                @include('partials.seasonality-table')
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500">
                <i class="fa-solid fa-scale-balanced mr-2"></i>Sector Comparison ({{ $ref->sector }})
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-center text-sm">
                    <thead class="text-xs text-slate-400 bg-slate-50">
                        <tr>
                            <th class="text-left px-3 py-2">Emiten</th>
                            <th class="py-2">Price</th>
                            <th class="py-2">PER</th>
                            <th class="py-2">PBV</th>
                            <th class="py-2">ROE</th>
                            <th class="py-2">Mkt Cap</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="bg-indigo-50">
                            <td class="text-left px-3 py-2 font-bold text-primary">{{ $cleanTicker }} <span class="text-[10px] bg-primary text-white rounded px-1.5 py-0.5">YOU</span></td>
                            <td class="py-2">{{ number_format($close) }}</td>
                            <td class="py-2 font-bold">{{ number_format((float) $ref->pe_ratio, 2) }}x</td>
                            <td class="py-2">{{ number_format((float) $ref->pb_ratio, 2) }}x</td>
                            <td class="py-2 {{ (float) $ref->roe > 15 ? 'text-emerald-600 font-bold' : '' }}">{{ number_format((float) $ref->roe, 2) }}%</td>
                            <td class="py-2">{{ \App\Support\Format::compactRupiah($ref->market_cap) }}</td>
                        </tr>
                        @foreach ($peers as $peer)
                            <tr>
                                <td class="text-left px-3 py-2 font-bold"><a href="{{ route('stocks.show', $peer->cleanTicker()) }}" class="hover:text-primary">{{ $peer->cleanTicker() }}</a></td>
                                <td class="py-2">{{ number_format((float) $peer->price->close_price) }}</td>
                                <td class="py-2 text-slate-400">{{ number_format((float) $peer->pe_ratio, 2) }}x</td>
                                <td class="py-2 text-slate-400">{{ number_format((float) $peer->pb_ratio, 2) }}x</td>
                                <td class="py-2 {{ (float) $peer->roe > 15 ? 'text-emerald-600' : 'text-slate-400' }}">{{ number_format((float) $peer->roe, 2) }}%</td>
                                <td class="py-2 text-slate-400">{{ \App\Support\Format::compactRupiah($peer->market_cap) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex justify-between items-center text-xs font-bold uppercase text-slate-500">
                <span><i class="fa-solid fa-brain mr-2 text-primary"></i>AI Verdict</span>
                <span class="{{ $verdictColor }}">{{ $verdict }}</span>
            </div>
            <div class="p-4 space-y-3">
                @foreach ([['Trend', $score->trend, 35, 'bg-primary'], ['Momentum', $score->momentum, 25, 'bg-sky-500'], ['Smart Flow', $score->flow, 25, 'bg-amber-500'], ['Fundamental', $score->fundamental, 15, 'bg-emerald-500']] as [$label, $val, $max, $color])
                    <div>
                        {{-- Rounded for display only: the awards are graded now, so
                             $val carries a fraction the bar width still uses. --}}
                        <div class="flex justify-between text-[11px] font-bold text-slate-500 mb-1"><span>{{ $label }}</span><span>{{ round($val, 1) }}/{{ $max }}</span></div>
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full {{ $color }}" style="width: {{ ($val / $max) * 100 }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500"><i class="fa-solid fa-chart-pie mr-2"></i>Market Insight</div>
            <div class="p-4 grid grid-cols-2 gap-x-4 text-sm">
                <div class="space-y-2 border-r border-slate-100 pr-3">
                    <div class="flex justify-between"><span class="text-slate-400">Volume</span><span class="font-bold">{{ number_format($price->volume) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">Cap</span><span class="font-bold">{{ \App\Support\Format::compactRupiah($ref->market_cap) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">PBV</span><span class="font-bold">{{ number_format((float) $ref->pb_ratio, 2) }}x</span></div>
                </div>
                <div class="space-y-2 pl-1">
                    <div class="flex justify-between"><span class="text-slate-400">Trend</span><span class="font-bold {{ match ($trendUp) {
                        true => 'text-emerald-600',
                        false => 'text-red-600',
                        default => 'text-slate-400',
                    } }}">{{ match ($trendUp) { true => 'BULLISH', false => 'BEARISH', default => '-' } }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">RSI</span><span class="font-bold">{{ round((float) $price->rsi_14) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">Stoch</span><span class="font-bold">{{ round((float) $price->stoch_k) }}</span></div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500">Levels &amp; Safety</div>
            <div class="p-3">
                <table class="w-full text-center text-xs border-collapse">
                    <tr>
                        <td class="p-2 border border-slate-100 bg-red-50 text-red-600 font-bold">{{ number_format($pivots['r2']) }}</td>
                        <td class="p-2 border border-slate-100 bg-red-50 text-red-600 font-bold">{{ number_format($pivots['r1']) }}</td>
                        <td class="p-2 border border-slate-100 bg-blue-500 text-white font-bold">{{ number_format($pivots['pivot']) }}</td>
                        <td class="p-2 border border-slate-100 bg-emerald-50 text-emerald-600 font-bold">{{ number_format($pivots['s1']) }}</td>
                        <td class="p-2 border border-slate-100 bg-emerald-50 text-emerald-600 font-bold">{{ number_format($pivots['s2']) }}</td>
                    </tr>
                </table>
                <div class="text-center text-[10px] text-slate-400 mt-1">R2 - R1 - PIVOT - S1 - S2</div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500"><i class="fa-solid fa-clock-rotate-left mr-2"></i>1-Year Backtest (MA20)</div>
            <div class="p-4 flex justify-between text-center">
                <div><div class="text-[10px] text-slate-400">TRADES</div><div class="font-bold">{{ $backtest['trades'] }}</div></div>
                <div><div class="text-[10px] text-slate-400">WIN RATE</div><div class="font-bold">{{ $backtest['win_rate'] }}%</div></div>
                <div><div class="text-[10px] text-slate-400">PROFIT</div><div class="font-bold {{ $backtest['roi_pct'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($backtest['roi_pct'], 1) }}%</div></div>
                <div><div class="text-[10px] text-slate-400">SIGNAL</div><span class="text-[10px] font-bold px-2 py-1 rounded {{ $backtest['holding'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $backtest['holding'] ? 'HOLD' : 'WAIT' }}</span></div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500"><i class="fa-solid fa-newspaper mr-2"></i>News Sentiment</div>
            <div class="p-4 text-center">
                @php
                    $sentScore = (int) ($price->sentiment_score ?? 0);
                    [$sLabel, $sColor, $sIcon] = match (true) {
                        $sentScore >= 2 => ['VERY POSITIVE', 'text-emerald-600', 'fa-face-laugh-beam'],
                        $sentScore == 1 => ['POSITIVE', 'text-primary', 'fa-face-smile'],
                        $sentScore == 0 => ['NEUTRAL', 'text-slate-500', 'fa-face-meh'],
                        $sentScore == -1 => ['NEGATIVE', 'text-amber-600', 'fa-face-frown'],
                        default => ['VERY NEGATIVE', 'text-red-600', 'fa-face-dizzy'],
                    };
                @endphp
                <h4 class="font-bold text-lg {{ $sColor }}"><i class="fa-solid {{ $sIcon }}"></i> {{ $sLabel }}</h4>
                <div class="text-xs text-slate-400">AI Score: {{ $sentScore }}</div>
                @if ($sentScore != 0 && $price->last_news_title)
                    <div class="mt-2 text-left bg-slate-50 border border-slate-100 rounded-lg p-2 text-xs italic text-slate-600">"{{ \Illuminate\Support\Str::limit($price->last_news_title, 80) }}"</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div x-data="{ open: false }" @open-buy-modal.window="open = true" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div @click.outside="open = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-5">
        <div class="flex items-center justify-between mb-3"><h3 class="font-bold">Quick Buy</h3><button @click="open = false"><i class="fa-solid fa-xmark text-slate-400"></i></button></div>
        <form method="POST" action="{{ route('portfolio.trade') }}">
            @csrf
            <input type="hidden" name="action" value="buy">
            <input type="hidden" name="ticker" value="{{ $cleanTicker }}">
            <label class="block text-xs font-bold text-slate-500 mb-1">Price</label>
            <input type="number" name="price" step="0.01" value="{{ $close }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-3 font-bold">
            <label class="block text-xs font-bold text-slate-500 mb-1">Lot</label>
            <input type="number" name="lot" value="1" min="1" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-4 font-bold">
            <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2 hover:bg-indigo-700 transition">Execute</button>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://s3.tradingview.com/tv.js"></script>
<script>
new TradingView.widget({
    autosize: true,
    symbol: 'IDX:{{ $cleanTicker }}',
    interval: 'D',
    timezone: 'Asia/Jakarta',
    theme: 'light',
    style: '1',
    locale: 'id',
    toolbar_bg: '#f1f3f6',
    enable_publishing: false,
    container_id: 'tv_chart',
    studies: ['MASimple@tv-basicstudies', 'RSI@tv-basicstudies'],
});
function openBuyModal(ticker, price) {
    window.dispatchEvent(new CustomEvent('open-buy-modal', { detail: { ticker, price } }));
}
</script>
@endpush
@endsection
