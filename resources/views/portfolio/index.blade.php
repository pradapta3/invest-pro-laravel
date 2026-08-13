@extends('layouts.app')

@section('title', 'IDX Invest — Portfolio')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-extrabold">Invest<span class="text-primary">Pro</span></h1>
    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-deposit-modal'))" class="rounded-full bg-slate-900 text-white font-bold px-4 py-2 text-sm hover:bg-slate-700 transition">
        <x-icon name="plus" class="mr-2 w-4 h-4" :solid="true" />Top Up
    </button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
    <div class="rounded-2xl p-5 bg-gradient-to-br from-slate-800 to-slate-900 text-white">
        <div class="text-xs uppercase font-bold text-white/60 mb-2">Total Portfolio Value</div>
        <div class="text-2xl font-extrabold font-mono">IDR {{ number_format($totalEquity / 1_000_000, 2) }}M</div>
        <div class="text-xs text-white/50 mt-2">Asset Allocation: {{ $holdings->count() }} Stocks</div>
    </div>
    <div class="rounded-2xl p-5 bg-white border border-slate-200">
        <div class="text-xs uppercase font-bold text-slate-400 mb-2">Unrealized P/L</div>
        <div class="text-2xl font-extrabold font-mono {{ $unrealizedPl >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
            {{ $unrealizedPl >= 0 ? '+' : '' }}{{ number_format($unrealizedPl / 1_000_000, 2) }}M
        </div>
        <div class="text-xs text-slate-400 mt-2">Floating Profit/Loss</div>
    </div>
    <div class="rounded-2xl p-5 bg-white border border-slate-200">
        <div class="flex justify-between items-start">
            <div>
                <div class="text-xs uppercase font-bold text-slate-400 mb-2">Realized Profit</div>
                <div class="text-xl font-extrabold font-mono {{ (float) $wallet->realized_pl >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ (float) $wallet->realized_pl >= 0 ? '+' : '' }}{{ number_format($wallet->realized_pl / 1_000_000, 2) }}M
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase font-bold text-slate-400">Win Rate</div>
                <div class="font-mono font-bold text-lg">{{ round($winRate) }}%</div>
            </div>
        </div>
        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden mt-3">
            <div class="h-full bg-emerald-500" style="width: {{ $winRate }}%"></div>
        </div>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-2xl mb-4 overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-bold">Performance Analytics</h2>
            <div class="flex items-center gap-3 mt-1 text-xs text-slate-400">
                <span><x-icon name="circle" class="text-primary mr-1 w-4 h-4" :solid="true" />Portfolio</span>
                <span><x-icon name="circle" class="text-slate-300 mr-1 w-4 h-4" :solid="true" />IHSG</span>
                <span id="lblPort" class="font-mono font-bold ml-2">-</span>
            </div>
        </div>
        <div class="flex gap-1 bg-slate-100 rounded-lg p-1">
            @foreach (['1d' => '1D', '1wk' => '1W', '1mo' => '1M', 'ytd' => 'YTD'] as $key => $label)
                <button type="button" onclick="loadChart('{{ $key }}', this)" class="filter-btn px-3 py-1 rounded-md text-xs font-bold {{ $key === '1mo' ? 'bg-white text-primary shadow-sm' : 'text-slate-500' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    <div class="p-4 relative" style="height: 300px;">
        <canvas id="perfChart"></canvas>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-2xl mb-4 overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex justify-between items-center">
        <h2 class="font-bold">Current Holdings</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                <tr>
                    <th class="text-left px-4 py-3">Asset</th>
                    <th class="text-right px-2 py-3">Lots</th>
                    <th class="text-right px-2 py-3">Avg</th>
                    <th class="text-right px-2 py-3">Last</th>
                    <th class="text-right px-2 py-3">Mkt Val</th>
                    <th class="text-right px-2 py-3">P/L</th>
                    <th class="text-center px-4 py-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($holdings as $h)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="bg-indigo-50 text-primary font-extrabold text-xs px-2.5 py-1 rounded-lg">{{ str_replace('.JK', '', $h->position->ticker) }}</span>
                            <div class="text-xs text-slate-400 mt-1 truncate max-w-[10rem]">{{ $h->companyName }}</div>
                        </td>
                        <td class="text-right px-2 py-3 font-mono font-bold">{{ number_format($h->position->lots) }}</td>
                        <td class="text-right px-2 py-3 font-mono text-slate-400">{{ number_format($h->position->avg_price) }}</td>
                        <td class="text-right px-2 py-3 font-mono font-bold">
                            {{ number_format($h->currentPrice) }}
                            @if ($h->isLivePrice)<x-icon name="circle" class="text-emerald-500 text-[8px] ml-1 w-4 h-4" :solid="true" title="Live" />@endif
                        </td>
                        <td class="text-right px-2 py-3 font-mono font-bold">{{ number_format($h->marketValue / 1_000_000, 1) }} Jt</td>
                        <td class="text-right px-2 py-3">
                            <div class="font-mono font-bold {{ $h->profitLoss >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($h->profitLoss) }}</div>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $h->profitLoss >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }}">{{ number_format($h->profitLossPct, 2) }}%</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="openOrder('buy', '{{ str_replace('.JK', '', $h->position->ticker) }}', {{ $h->currentPrice }})" class="text-xs font-bold text-primary px-2 py-1 hover:bg-indigo-50 rounded">Buy</button>
                            <button onclick="openOrder('sell', '{{ str_replace('.JK', '', $h->position->ticker) }}', {{ $h->currentPrice }}, {{ $h->position->lots }})" class="text-xs font-bold text-red-600 px-2 py-1 hover:bg-red-50 rounded">Sell</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-10 text-slate-400 font-bold">Portfolio Kosong. Silakan beli saham dulu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-2xl mb-8 overflow-hidden">
    <div class="p-4 border-b border-slate-100"><h2 class="font-bold"><x-icon name="clock-rotate-left" class="mr-2 text-slate-400 w-4 h-4" :solid="true" />Transaction History</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                <tr><th class="text-left px-4 py-2">Time</th><th class="text-left py-2">Type</th><th class="text-left py-2">Asset</th><th class="text-right py-2">Price</th><th class="text-right py-2">Lot</th><th class="text-right px-4 py-2">P/L</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($history as $h)
                    <tr>
                        <td class="px-4 py-2 font-mono text-slate-400">{{ $h->date->format('d M H:i') }}</td>
                        <td class="py-2"><span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $h->type->value === 'BUY' ? 'bg-indigo-50 text-primary' : 'bg-red-50 text-red-600' }}">{{ $h->type->value }}</span></td>
                        <td class="py-2 font-bold">{{ str_replace('.JK', '', $h->ticker) }}</td>
                        <td class="text-right py-2 font-mono">{{ number_format($h->price) }}</td>
                        <td class="text-right py-2 font-mono">{{ number_format($h->lots) }}</td>
                        <td class="text-right px-4 py-2 font-mono {{ $h->profit_loss > 0 ? 'text-emerald-600' : ($h->profit_loss < 0 ? 'text-red-600' : 'text-slate-400') }}">{{ number_format($h->profit_loss) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-8 text-slate-400">Belum ada transaksi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<datalist id="stockOptions">
    @foreach ($tickerOptions as $opt)
        <option value="{{ $opt->cleanTicker() }}">{{ $opt->nama_perusahaan }}</option>
    @endforeach
</datalist>

<div x-data="{ open: false, action: 'buy', ticker: '', price: 0, lot: 0 }" x-cloak
     @open-order-modal.window="action = $event.detail.action; ticker = $event.detail.ticker; price = $event.detail.price; lot = ''; open = true"
     x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div @click.outside="open = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold" :class="action === 'sell' ? 'text-red-600' : 'text-primary'" x-text="action === 'sell' ? 'SELL' : 'BUY'"></h3>
            <button @click="open = false"><x-icon name="xmark" class="text-slate-400 w-4 h-4" :solid="true" /></button>
        </div>
        <form method="POST" action="{{ route('portfolio.trade') }}">
            @csrf
            <input type="hidden" name="action" :value="action">
            <label class="block text-xs font-bold text-slate-500 mb-1">Ticker</label>
            <input type="text" name="ticker" list="stockOptions" x-model="ticker" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-3 font-bold uppercase">
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Price</label>
                    <input type="number" name="price" step="0.01" x-model="price" required class="w-full rounded-lg border border-slate-200 px-3 py-2 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Lots</label>
                    <input type="number" name="lot" x-model="lot" min="1" required class="w-full rounded-lg border border-slate-200 px-3 py-2 font-bold">
                </div>
            </div>
            <button type="submit" class="w-full rounded-lg font-bold py-2 text-white transition" :class="action === 'sell' ? 'bg-red-600 hover:bg-red-700' : 'bg-primary hover:bg-indigo-700'">
                <span x-text="action === 'sell' ? 'CONFIRM SELL' : 'CONFIRM BUY'"></span>
            </button>
        </form>
    </div>
</div>

<div x-data="{ open: false }" x-cloak @open-deposit-modal.window="open = true" x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div @click.outside="open = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-5">
        <div class="flex items-center justify-between mb-3"><h3 class="font-bold">Add Cash</h3><button @click="open = false"><x-icon name="xmark" class="text-slate-400 w-4 h-4" :solid="true" /></button></div>
        <form method="POST" action="{{ route('portfolio.deposit') }}">
            @csrf
            <label class="block text-xs font-bold text-slate-500 mb-1">Amount (IDR)</label>
            <input type="number" name="amount" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-4 font-bold">
            <button type="submit" class="w-full rounded-lg bg-slate-900 text-white font-bold py-2 hover:bg-slate-700 transition">CONFIRM DEPOSIT</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openOrder(action, ticker, price, lot = 0) {
    window.dispatchEvent(new CustomEvent('open-order-modal', { detail: { action, ticker, price, lot } }));
}

let perfChart = null;
async function loadChart(range, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.className = 'filter-btn px-3 py-1 rounded-md text-xs font-bold text-slate-500');
    if (btn) btn.className = 'filter-btn px-3 py-1 rounded-md text-xs font-bold bg-white text-primary shadow-sm';

    document.getElementById('lblPort').innerText = 'Updating...';

    try {
        const res = await fetch(`{{ route('api.portfolio.chart') }}?range=${range}`);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        renderChart(data);
    } catch (e) {
        document.getElementById('lblPort').innerText = 'Data Unavailable';
    }
}

function renderChart(data) {
    const ctx = document.getElementById('perfChart').getContext('2d');
    const lastP = data.last_port;
    const lbl = document.getElementById('lblPort');
    lbl.innerText = (lastP >= 0 ? '+' : '') + lastP + '%';
    lbl.className = 'font-mono font-bold ml-2 ' + (lastP >= 0 ? 'text-emerald-600' : 'text-red-600');

    if (perfChart) perfChart.destroy();

    let grad = ctx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, 'rgba(79, 70, 229, 0.15)');
    grad.addColorStop(1, 'rgba(79, 70, 229, 0)');

    perfChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                { label: 'Portfolio', data: data.portfolio, borderColor: '#4f46e5', borderWidth: 2.5, backgroundColor: grad, fill: true, tension: 0.3, pointRadius: 0 },
                { label: 'IHSG', data: data.ihsg, borderColor: '#94a3b8', borderWidth: 2, borderDash: [4, 4], tension: 0.3, pointRadius: 0 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false } }, y: { position: 'right', ticks: { callback: (v) => v + '%' } } },
        },
    });
}
// The libraries are bundled now, and the bundle is loaded as a module.
// Modules are deferred, so they run after the HTML is parsed but before
// DOMContentLoaded — meaning Chart does not exist while this inline script
// is being parsed, only by the time this listener fires.
document.addEventListener('DOMContentLoaded', () => loadChart('1mo', null));
</script>
@endpush
@endsection
