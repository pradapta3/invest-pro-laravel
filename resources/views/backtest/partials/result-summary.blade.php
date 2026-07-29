{{-- Expects: $result (BacktestResult) --}}
@if ($result->tradeCount() === 0)
    <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-400">
        Tidak ada sinyal yang terpicu pada periode ini (atau belum ada data historis — jalankan
        <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">php artisan idx:backfill-price-history</code> dulu).
    </div>
@else
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs font-bold text-slate-400 uppercase">Jumlah Trade</div>
            <div class="text-2xl font-extrabold">{{ $result->tradeCount() }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs font-bold text-slate-400 uppercase">Win Rate</div>
            <div class="text-2xl font-extrabold">{{ round($result->winRate(), 1) }}%</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs font-bold text-slate-400 uppercase">Total Return</div>
            <div class="text-2xl font-extrabold {{ $result->totalReturnPct() >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ round($result->totalReturnPct(), 2) }}%</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs font-bold text-slate-400 uppercase">Max Drawdown</div>
            <div class="text-2xl font-extrabold text-red-600">{{ round($result->maxDrawdownPct(), 2) }}%</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs font-bold text-slate-400 uppercase">Avg Return / Trade</div>
            <div class="text-lg font-extrabold {{ $result->avgReturnPct() >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ round($result->avgReturnPct(), 2) }}%</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs font-bold text-slate-400 uppercase">Profit Factor</div>
            <div class="text-lg font-extrabold">{{ $result->profitFactor() !== null ? round($result->profitFactor(), 2) : '∞' }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs font-bold text-slate-400 uppercase">Sharpe (per-trade)</div>
            <div class="text-lg font-extrabold">{{ $result->sharpeRatio() !== null ? round($result->sharpeRatio(), 2) : 'n/a' }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs font-bold text-slate-400 uppercase">Avg Hold (hari)</div>
            <div class="text-lg font-extrabold">{{ round($result->avgHoldingDays(), 1) }}</div>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-4" style="height: 280px;">
        <canvas id="equityChart"></canvas>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-sm">Semua Trade ({{ $result->tradeCount() }})</div>
        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-400 sticky top-0">
                    <tr>
                        <th class="text-left px-4 py-2">Ticker</th>
                        <th class="text-left py-2">Entry</th>
                        <th class="text-left py-2">Exit</th>
                        <th class="text-left py-2">Alasan Keluar</th>
                        <th class="text-right py-2">Hold (hari)</th>
                        <th class="text-right px-4 py-2">Return</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($result->trades->sortByDesc(fn($t) => $t->exitDate->timestamp) as $trade)
                        <tr>
                            <td class="px-4 py-2 font-bold">{{ str_replace('.JK', '', $trade->ticker) }}</td>
                            <td class="py-2 text-slate-500">{{ $trade->entryDate->format('d M Y') }} @ {{ number_format($trade->entryPrice) }}</td>
                            <td class="py-2 text-slate-500">{{ $trade->exitDate->format('d M Y') }} @ {{ number_format($trade->exitPrice) }}</td>
                            <td class="py-2">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $trade->exitReason === 'take_profit' ? 'bg-emerald-50 text-emerald-600' : ($trade->exitReason === 'stop_loss' ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-500') }}">
                                    {{ str_replace('_', ' ', $trade->exitReason) }}
                                </span>
                            </td>
                            <td class="text-right py-2">{{ $trade->holdingDays }}</td>
                            <td class="text-right px-4 py-2 font-bold {{ $trade->isWin() ? 'text-emerald-600' : 'text-red-600' }}">{{ round($trade->returnPct(), 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
