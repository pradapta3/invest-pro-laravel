<?php $__env->startSection('title', str_replace('.JK', '', $ref->ticker).' — Analisa Lengkap'); ?>

<?php $__env->startSection('content'); ?>
<?php
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
    $flowStat = (float) $price->vwap > 0 && $close > (float) $price->vwap ? 'ACCUM' : 'DIST';
    $trendUp = $close > (float) $price->ma20;
?>

<div class="bg-white border border-slate-200 rounded-2xl mb-4 p-4 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <a href="<?php echo e(route('dashboard')); ?>" class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center hover:bg-slate-200"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-extrabold"><?php echo e($cleanTicker); ?> <span class="text-base font-normal text-slate-400"><?php echo e($ref->nama_perusahaan); ?></span></h1>
            <div class="flex items-center gap-2 mt-1">
                <span class="text-xs bg-slate-100 border border-slate-200 rounded-full px-2 py-0.5"><?php echo e($ref->sector ?? '-'); ?></span>
                <span class="text-xs rounded-full px-2 py-0.5 <?php echo e($flowStat === 'ACCUM' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'); ?>"><?php echo e($flowStat); ?></span>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-4">
        <div class="text-right">
            <div class="text-2xl font-extrabold <?php echo e($change >= 0 ? 'text-emerald-600' : 'text-red-600'); ?>">Rp <?php echo e(number_format($close)); ?></div>
            <div class="text-xs font-bold <?php echo e($change >= 0 ? 'text-emerald-600' : 'text-red-600'); ?>"><?php echo e($change >= 0 ? '+' : ''); ?><?php echo e(number_format($change)); ?> (<?php echo e(number_format($changePct, 2)); ?>%)</div>
        </div>
        <div class="w-12 h-12 rounded-full flex items-center justify-center font-extrabold text-lg <?php echo e($verdictColor); ?> border-4 border-slate-100"><?php echo e($score->total()); ?></div>
        <button onclick="openBuyModal('<?php echo e($cleanTicker); ?>', <?php echo e($close); ?>)" class="rounded-lg bg-primary text-white font-bold px-4 py-2 hover:bg-indigo-700 transition text-sm">BUY</button>
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
                <span class="normal-case font-medium">Best: <b class="text-emerald-600"><?php echo e(\Carbon\Carbon::create()->month($seasonality['best']['month'])->translatedFormat('M')); ?></b> · Worst: <b class="text-red-600"><?php echo e(\Carbon\Carbon::create()->month($seasonality['worst']['month'])->translatedFormat('M')); ?></b></span>
            </div>
            <div class="p-4">
                <?php echo $__env->make('partials.seasonality-table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500">
                <i class="fa-solid fa-scale-balanced mr-2"></i>Sector Comparison (<?php echo e($ref->sector); ?>)
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
                            <td class="text-left px-3 py-2 font-bold text-primary"><?php echo e($cleanTicker); ?> <span class="text-[10px] bg-primary text-white rounded px-1.5 py-0.5">YOU</span></td>
                            <td class="py-2"><?php echo e(number_format($close)); ?></td>
                            <td class="py-2 font-bold"><?php echo e(number_format((float) $ref->pe_ratio, 2)); ?>x</td>
                            <td class="py-2"><?php echo e(number_format((float) $ref->pb_ratio, 2)); ?>x</td>
                            <td class="py-2 <?php echo e((float) $ref->roe > 15 ? 'text-emerald-600 font-bold' : ''); ?>"><?php echo e(number_format((float) $ref->roe, 2)); ?>%</td>
                            <td class="py-2"><?php echo e(\App\Support\Format::compactRupiah($ref->market_cap)); ?></td>
                        </tr>
                        <?php $__currentLoopData = $peers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $peer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="text-left px-3 py-2 font-bold"><a href="<?php echo e(route('stocks.show', $peer->cleanTicker())); ?>" class="hover:text-primary"><?php echo e($peer->cleanTicker()); ?></a></td>
                                <td class="py-2"><?php echo e(number_format((float) $peer->price->close_price)); ?></td>
                                <td class="py-2 text-slate-400"><?php echo e(number_format((float) $peer->pe_ratio, 2)); ?>x</td>
                                <td class="py-2 text-slate-400"><?php echo e(number_format((float) $peer->pb_ratio, 2)); ?>x</td>
                                <td class="py-2 <?php echo e((float) $peer->roe > 15 ? 'text-emerald-600' : 'text-slate-400'); ?>"><?php echo e(number_format((float) $peer->roe, 2)); ?>%</td>
                                <td class="py-2 text-slate-400"><?php echo e(\App\Support\Format::compactRupiah($peer->market_cap)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex justify-between items-center text-xs font-bold uppercase text-slate-500">
                <span><i class="fa-solid fa-brain mr-2 text-primary"></i>AI Verdict</span>
                <span class="<?php echo e($verdictColor); ?>"><?php echo e($verdict); ?></span>
            </div>
            <div class="p-4 space-y-3">
                <?php $__currentLoopData = [['Trend', $score->trend, 35, 'bg-primary'], ['Momentum', $score->momentum, 25, 'bg-sky-500'], ['Smart Flow', $score->flow, 25, 'bg-amber-500'], ['Fundamental', $score->fundamental, 15, 'bg-emerald-500']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $val, $max, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div>
                        <div class="flex justify-between text-[11px] font-bold text-slate-500 mb-1"><span><?php echo e($label); ?></span><span><?php echo e($val); ?>/<?php echo e($max); ?></span></div>
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full <?php echo e($color); ?>" style="width: <?php echo e(($val / $max) * 100); ?>%"></div></div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500"><i class="fa-solid fa-chart-pie mr-2"></i>Market Insight</div>
            <div class="p-4 grid grid-cols-2 gap-x-4 text-sm">
                <div class="space-y-2 border-r border-slate-100 pr-3">
                    <div class="flex justify-between"><span class="text-slate-400">Volume</span><span class="font-bold"><?php echo e(number_format($price->volume)); ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-400">Cap</span><span class="font-bold"><?php echo e(\App\Support\Format::compactRupiah($ref->market_cap)); ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-400">PBV</span><span class="font-bold"><?php echo e(number_format((float) $ref->pb_ratio, 2)); ?>x</span></div>
                </div>
                <div class="space-y-2 pl-1">
                    <div class="flex justify-between"><span class="text-slate-400">Trend</span><span class="font-bold <?php echo e($trendUp ? 'text-emerald-600' : 'text-red-600'); ?>"><?php echo e($trendUp ? 'BULLISH' : 'BEARISH'); ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-400">RSI</span><span class="font-bold"><?php echo e(round((float) $price->rsi_14)); ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-400">Stoch</span><span class="font-bold"><?php echo e(round((float) $price->stoch_k)); ?></span></div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500">Levels &amp; Safety</div>
            <div class="p-3">
                <table class="w-full text-center text-xs border-collapse">
                    <tr>
                        <td class="p-2 border border-slate-100 bg-red-50 text-red-600 font-bold"><?php echo e(number_format($pivots['r2'])); ?></td>
                        <td class="p-2 border border-slate-100 bg-red-50 text-red-600 font-bold"><?php echo e(number_format($pivots['r1'])); ?></td>
                        <td class="p-2 border border-slate-100 bg-blue-500 text-white font-bold"><?php echo e(number_format($pivots['pivot'])); ?></td>
                        <td class="p-2 border border-slate-100 bg-emerald-50 text-emerald-600 font-bold"><?php echo e(number_format($pivots['s1'])); ?></td>
                        <td class="p-2 border border-slate-100 bg-emerald-50 text-emerald-600 font-bold"><?php echo e(number_format($pivots['s2'])); ?></td>
                    </tr>
                </table>
                <div class="text-center text-[10px] text-slate-400 mt-1">R2 - R1 - PIVOT - S1 - S2</div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500"><i class="fa-solid fa-clock-rotate-left mr-2"></i>1-Year Backtest (MA20)</div>
            <div class="p-4 flex justify-between text-center">
                <div><div class="text-[10px] text-slate-400">TRADES</div><div class="font-bold"><?php echo e($backtest['trades']); ?></div></div>
                <div><div class="text-[10px] text-slate-400">WIN RATE</div><div class="font-bold"><?php echo e($backtest['win_rate']); ?>%</div></div>
                <div><div class="text-[10px] text-slate-400">PROFIT</div><div class="font-bold <?php echo e($backtest['roi_pct'] >= 0 ? 'text-emerald-600' : 'text-red-600'); ?>"><?php echo e(number_format($backtest['roi_pct'], 1)); ?>%</div></div>
                <div><div class="text-[10px] text-slate-400">SIGNAL</div><span class="text-[10px] font-bold px-2 py-1 rounded <?php echo e($backtest['holding'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'); ?>"><?php echo e($backtest['holding'] ? 'HOLD' : 'WAIT'); ?></span></div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500"><i class="fa-solid fa-newspaper mr-2"></i>News Sentiment</div>
            <div class="p-4 text-center">
                <?php
                    $sentScore = (int) ($price->sentiment_score ?? 0);
                    [$sLabel, $sColor, $sIcon] = match (true) {
                        $sentScore >= 2 => ['VERY POSITIVE', 'text-emerald-600', 'fa-face-laugh-beam'],
                        $sentScore == 1 => ['POSITIVE', 'text-primary', 'fa-face-smile'],
                        $sentScore == 0 => ['NEUTRAL', 'text-slate-500', 'fa-face-meh'],
                        $sentScore == -1 => ['NEGATIVE', 'text-amber-600', 'fa-face-frown'],
                        default => ['VERY NEGATIVE', 'text-red-600', 'fa-face-dizzy'],
                    };
                ?>
                <h4 class="font-bold text-lg <?php echo e($sColor); ?>"><i class="fa-solid <?php echo e($sIcon); ?>"></i> <?php echo e($sLabel); ?></h4>
                <div class="text-xs text-slate-400">AI Score: <?php echo e($sentScore); ?></div>
                <?php if($sentScore != 0 && $price->last_news_title): ?>
                    <div class="mt-2 text-left bg-slate-50 border border-slate-100 rounded-lg p-2 text-xs italic text-slate-600">"<?php echo e(\Illuminate\Support\Str::limit($price->last_news_title, 80)); ?>"</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div x-data="{ open: false }" @open-buy-modal.window="open = true" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div @click.outside="open = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-5">
        <div class="flex items-center justify-between mb-3"><h3 class="font-bold">Quick Buy</h3><button @click="open = false"><i class="fa-solid fa-xmark text-slate-400"></i></button></div>
        <form method="POST" action="<?php echo e(route('portfolio.trade')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="buy">
            <input type="hidden" name="ticker" value="<?php echo e($cleanTicker); ?>">
            <label class="block text-xs font-bold text-slate-500 mb-1">Price</label>
            <input type="number" name="price" step="0.01" value="<?php echo e($close); ?>" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-3 font-bold">
            <label class="block text-xs font-bold text-slate-500 mb-1">Lot</label>
            <input type="number" name="lot" value="1" min="1" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-4 font-bold">
            <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2 hover:bg-indigo-700 transition">Execute</button>
        </form>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script src="https://s3.tradingview.com/tv.js"></script>
<script>
new TradingView.widget({
    autosize: true,
    symbol: 'IDX:<?php echo e($cleanTicker); ?>',
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
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/stocks/detail.blade.php ENDPATH**/ ?>