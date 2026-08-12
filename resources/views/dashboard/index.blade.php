@extends('layouts.app')

@section('title', 'IDX Invest — Dashboard')

@section('content')
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row gap-3 items-center">
        <form method="GET" class="flex items-center gap-2 w-full md:w-64">
            <input type="hidden" name="f" value="{{ $filter }}">
            <div class="relative w-full">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="q" value="{{ $query }}" placeholder="Cari Emiten..."
                       onchange="this.form.submit()"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>
        </form>

        <div class="flex gap-2 overflow-x-auto w-full">
            <x-filter-chip :href="'?q='.$query.'&f=bsjp'" :active="$filter === 'bsjp'" variant="purple" icon="fa-moon">BSJP</x-filter-chip>
            <x-filter-chip :href="'?q='.$query.'&f=stockpick'" :active="$filter === 'stockpick'" variant="gold" icon="fa-trophy">Top Picks</x-filter-chip>
            <x-filter-chip :href="'?q='.$query.'&f=watchlist'" :active="$filter === 'watchlist'" icon="fa-star">Watchlist</x-filter-chip>
            <x-filter-chip :href="'?q='.$query.'&f=all'" :active="$filter === 'all'">All Stocks</x-filter-chip>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left font-bold px-4 py-3">Ticker</th>
                    <th class="font-bold px-2 py-3 text-center">Score</th>
                    <th class="text-left font-bold px-2 py-3">Trend &amp; Price</th>
                    <th class="text-left font-bold px-2 py-3">Trading Plan</th>
                    <th class="text-left font-bold px-2 py-3">Flow</th>
                    <th class="text-right font-bold px-4 py-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($stocks as $stock)
                    @php
                        $ref = $stock->stockRef;
                        $score = $ta->calculateScore($stock, $ref);
                        $plan = $ta->buildTradingPlan($stock, $filter === 'bsjp' ? 'bsjp' : 'swing');
                        $trendUp = (float) $stock->close_price > (float) $stock->ma20;
                        $vwapStat = $stock->moneyFlow();
                        $cleanTicker = str_replace('.JK', '', $stock->ticker);
                        $isWatchlisted = in_array($stock->ticker, $watchlistedTickers, true);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <form method="POST" action="{{ route('dashboard.toggle-watchlist', $cleanTicker) }}">
                                    @csrf
                                    <button type="submit" class="text-lg {{ $isWatchlisted ? 'text-amber-400' : 'text-slate-200 hover:text-amber-300' }}">
                                        <i class="fa-{{ $isWatchlisted ? 'solid' : 'regular' }} fa-star"></i>
                                    </button>
                                </form>
                                <div>
                                    <a href="{{ route('stocks.show', $cleanTicker) }}" class="font-bold text-slate-900 hover:text-primary">{{ $cleanTicker }}</a>
                                    <div class="text-xs text-slate-400 truncate max-w-[9rem]">{{ $ref?->nama_perusahaan }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-2 py-3 text-center"><x-score-badge :score="$score->total()" class="mx-auto" /></td>
                        <td class="px-2 py-3">
                            <x-sparkline :history="$stock->history_json ?? []" :color="$trendUp ? '#10b981' : '#ef4444'" />
                            <div class="font-bold text-xs mt-1 {{ $trendUp ? 'text-emerald-600' : 'text-red-600' }}">Rp {{ number_format($stock->close_price) }}</div>
                        </td>
                        <td class="px-2 py-3 text-xs">
                            <div class="flex justify-between gap-2"><span class="text-slate-400">Entry</span><span class="font-bold text-primary">{{ $plan->entryText() }}</span></div>
                            <div class="flex justify-between gap-2"><span class="text-slate-400">TP</span><span class="font-bold text-emerald-600">{{ number_format($plan->takeProfit) }} <span class="text-[10px]">+{{ $plan->takeProfitPct() }}%</span></span></div>
                            <div class="flex justify-between gap-2"><span class="text-slate-400">SL</span><span class="font-bold text-red-600">{{ number_format($plan->stopLoss) }} <span class="text-[10px]">-{{ $plan->stopLossPct() }}%</span></span></div>
                        </td>
                        <td class="px-2 py-3">
                            <div class="font-bold text-xs">Rp {{ \App\Support\Format::compactRupiah($stock->value_transaction) }}</div>
                            {{-- Neutral grey when VWAP has not been collected yet, rather
                                 than a red DIST badge asserting distribution for the whole
                                 exchange on the strength of a missing number. --}}
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ match ($vwapStat) {
                                'AKUM' => 'bg-emerald-50 text-emerald-600',
                                'DIST' => 'bg-red-50 text-red-600',
                                default => 'bg-slate-100 text-slate-400',
                            } }}" @if ($vwapStat === null) title="VWAP belum tersedia — jalankan idx:update-realtime-quotes" @endif>{{ $vwapStat ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button onclick="analyzeStock('{{ $cleanTicker }}')" class="w-8 h-8 rounded-lg bg-indigo-50 text-primary hover:bg-primary hover:text-white transition"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
                                <button onclick="sendSignal('{{ $cleanTicker }}')" class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-600 hover:text-white transition"><i class="fa-solid fa-paper-plane"></i></button>
                                <a href="{{ route('stocks.show', $cleanTicker) }}" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-700 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-chart-simple"></i></a>
                                <button onclick="openBuyModal('{{ $cleanTicker }}', {{ (float) $stock->close_price }})" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition"><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-10 text-slate-400">Data tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($stocks->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $stocks->links() }}
        </div>
    @endif
</div>

<div x-data="{ open: false, ticker: '', price: 0 }" x-cloak
     @open-buy-modal.window="ticker = $event.detail.ticker; price = $event.detail.price; open = true"
     x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div @click.outside="open = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold">Quick Buy</h3>
            <button @click="open = false"><i class="fa-solid fa-xmark text-slate-400"></i></button>
        </div>
        <form method="POST" action="{{ route('portfolio.trade') }}">
            @csrf
            <input type="hidden" name="action" value="buy">
            <input type="hidden" name="ticker" :value="ticker">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xl font-extrabold text-primary" x-text="ticker"></span>
                <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-1 rounded" x-text="price"></span>
            </div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Price</label>
            <input type="number" name="price" step="0.01" x-bind:value="price" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-3 font-bold">
            <label class="block text-xs font-bold text-slate-500 mb-1">Lot</label>
            <input type="number" name="lot" value="1" min="1" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-4 font-bold">
            <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2 hover:bg-indigo-700 transition">Execute</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openBuyModal(ticker, price) {
    window.dispatchEvent(new CustomEvent('open-buy-modal', { detail: { ticker, price } }));
}
</script>
@endpush
@endsection
