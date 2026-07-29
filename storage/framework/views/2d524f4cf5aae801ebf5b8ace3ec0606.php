<?php $__env->startSection('title', 'IDX Invest — Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row gap-3 items-center">
        <form method="GET" class="flex items-center gap-2 w-full md:w-64">
            <input type="hidden" name="f" value="<?php echo e($filter); ?>">
            <div class="relative w-full">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="q" value="<?php echo e($query); ?>" placeholder="Cari Emiten..."
                       onchange="this.form.submit()"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>
        </form>

        <div class="flex gap-2 overflow-x-auto w-full">
            <?php if (isset($component)) { $__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.filter-chip','data' => ['href' => '?q='.$query.'&f=bsjp','active' => $filter === 'bsjp','variant' => 'purple','icon' => 'fa-moon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filter-chip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('?q='.$query.'&f=bsjp'),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filter === 'bsjp'),'variant' => 'purple','icon' => 'fa-moon']); ?>BSJP <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8)): ?>
<?php $attributes = $__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8; ?>
<?php unset($__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8)): ?>
<?php $component = $__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8; ?>
<?php unset($__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.filter-chip','data' => ['href' => '?q='.$query.'&f=stockpick','active' => $filter === 'stockpick','variant' => 'gold','icon' => 'fa-trophy']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filter-chip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('?q='.$query.'&f=stockpick'),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filter === 'stockpick'),'variant' => 'gold','icon' => 'fa-trophy']); ?>Top Picks <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8)): ?>
<?php $attributes = $__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8; ?>
<?php unset($__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8)): ?>
<?php $component = $__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8; ?>
<?php unset($__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.filter-chip','data' => ['href' => '?q='.$query.'&f=watchlist','active' => $filter === 'watchlist','icon' => 'fa-star']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filter-chip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('?q='.$query.'&f=watchlist'),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filter === 'watchlist'),'icon' => 'fa-star']); ?>Watchlist <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8)): ?>
<?php $attributes = $__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8; ?>
<?php unset($__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8)): ?>
<?php $component = $__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8; ?>
<?php unset($__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.filter-chip','data' => ['href' => '?q='.$query.'&f=all','active' => $filter === 'all']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filter-chip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('?q='.$query.'&f=all'),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filter === 'all')]); ?>All Stocks <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8)): ?>
<?php $attributes = $__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8; ?>
<?php unset($__attributesOriginalb921c1d8f3114956c1eefb7c2c21f5b8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8)): ?>
<?php $component = $__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8; ?>
<?php unset($__componentOriginalb921c1d8f3114956c1eefb7c2c21f5b8); ?>
<?php endif; ?>
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
                <?php $__empty_1 = true; $__currentLoopData = $stocks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stock): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $ref = $stock->stockRef;
                        $score = $ta->calculateScore($stock, $ref);
                        $plan = $ta->buildTradingPlan($stock, $filter === 'bsjp' ? 'bsjp' : 'swing');
                        $trendUp = (float) $stock->close_price > (float) $stock->ma20;
                        $vwapStat = (float) $stock->vwap > 0 && (float) $stock->close_price > (float) $stock->vwap ? 'AKUM' : 'DIST';
                        $cleanTicker = str_replace('.JK', '', $stock->ticker);
                        $isWatchlisted = in_array($stock->ticker, $watchlistedTickers, true);
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <form method="POST" action="<?php echo e(route('dashboard.toggle-watchlist', $cleanTicker)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="text-lg <?php echo e($isWatchlisted ? 'text-amber-400' : 'text-slate-200 hover:text-amber-300'); ?>">
                                        <i class="fa-<?php echo e($isWatchlisted ? 'solid' : 'regular'); ?> fa-star"></i>
                                    </button>
                                </form>
                                <div>
                                    <a href="<?php echo e(route('stocks.show', $cleanTicker)); ?>" class="font-bold text-slate-900 hover:text-primary"><?php echo e($cleanTicker); ?></a>
                                    <div class="text-xs text-slate-400 truncate max-w-[9rem]"><?php echo e($ref?->nama_perusahaan); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-2 py-3 text-center"><?php if (isset($component)) { $__componentOriginal59ca2f248e77cbdaa40c8ba5261dda22 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal59ca2f248e77cbdaa40c8ba5261dda22 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.score-badge','data' => ['score' => $score->total(),'class' => 'mx-auto']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('score-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($score->total()),'class' => 'mx-auto']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal59ca2f248e77cbdaa40c8ba5261dda22)): ?>
<?php $attributes = $__attributesOriginal59ca2f248e77cbdaa40c8ba5261dda22; ?>
<?php unset($__attributesOriginal59ca2f248e77cbdaa40c8ba5261dda22); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal59ca2f248e77cbdaa40c8ba5261dda22)): ?>
<?php $component = $__componentOriginal59ca2f248e77cbdaa40c8ba5261dda22; ?>
<?php unset($__componentOriginal59ca2f248e77cbdaa40c8ba5261dda22); ?>
<?php endif; ?></td>
                        <td class="px-2 py-3">
                            <?php if (isset($component)) { $__componentOriginal298d4b9cf43466c1b93e4811c05e939c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal298d4b9cf43466c1b93e4811c05e939c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.sparkline','data' => ['history' => $stock->history_json ?? [],'color' => $trendUp ? '#10b981' : '#ef4444']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('sparkline'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['history' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stock->history_json ?? []),'color' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($trendUp ? '#10b981' : '#ef4444')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal298d4b9cf43466c1b93e4811c05e939c)): ?>
<?php $attributes = $__attributesOriginal298d4b9cf43466c1b93e4811c05e939c; ?>
<?php unset($__attributesOriginal298d4b9cf43466c1b93e4811c05e939c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal298d4b9cf43466c1b93e4811c05e939c)): ?>
<?php $component = $__componentOriginal298d4b9cf43466c1b93e4811c05e939c; ?>
<?php unset($__componentOriginal298d4b9cf43466c1b93e4811c05e939c); ?>
<?php endif; ?>
                            <div class="font-bold text-xs mt-1 <?php echo e($trendUp ? 'text-emerald-600' : 'text-red-600'); ?>">Rp <?php echo e(number_format($stock->close_price)); ?></div>
                        </td>
                        <td class="px-2 py-3 text-xs">
                            <div class="flex justify-between gap-2"><span class="text-slate-400">Entry</span><span class="font-bold text-primary"><?php echo e($plan->entryText()); ?></span></div>
                            <div class="flex justify-between gap-2"><span class="text-slate-400">TP</span><span class="font-bold text-emerald-600"><?php echo e(number_format($plan->takeProfit)); ?> <span class="text-[10px]">+<?php echo e($plan->takeProfitPct()); ?>%</span></span></div>
                            <div class="flex justify-between gap-2"><span class="text-slate-400">SL</span><span class="font-bold text-red-600"><?php echo e(number_format($plan->stopLoss)); ?> <span class="text-[10px]">-<?php echo e($plan->stopLossPct()); ?>%</span></span></div>
                        </td>
                        <td class="px-2 py-3">
                            <div class="font-bold text-xs">Rp <?php echo e(\App\Support\Format::compactRupiah($stock->value_transaction)); ?></div>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded <?php echo e($vwapStat === 'AKUM' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'); ?>"><?php echo e($vwapStat); ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button onclick="analyzeStock('<?php echo e($cleanTicker); ?>')" class="w-8 h-8 rounded-lg bg-indigo-50 text-primary hover:bg-primary hover:text-white transition"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
                                <button onclick="sendSignal('<?php echo e($cleanTicker); ?>')" class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-600 hover:text-white transition"><i class="fa-solid fa-paper-plane"></i></button>
                                <a href="<?php echo e(route('stocks.show', $cleanTicker)); ?>" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-700 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-chart-simple"></i></a>
                                <button onclick="openBuyModal('<?php echo e($cleanTicker); ?>', <?php echo e((float) $stock->close_price); ?>)" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition"><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="6" class="text-center py-10 text-slate-400">Data tidak ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($stocks->hasPages()): ?>
        <div class="p-4 border-t border-slate-100">
            <?php echo e($stocks->links()); ?>

        </div>
    <?php endif; ?>
</div>

<div x-data="{ open: false, ticker: '', price: 0 }" x-cloak
     @open-buy-modal.window="ticker = $event.detail.ticker; price = $event.detail.price; open = true"
     x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div @click.outside="open = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold">Quick Buy</h3>
            <button @click="open = false"><i class="fa-solid fa-xmark text-slate-400"></i></button>
        </div>
        <form method="POST" action="<?php echo e(route('portfolio.trade')); ?>">
            <?php echo csrf_field(); ?>
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

<?php $__env->startPush('scripts'); ?>
<script>
function openBuyModal(ticker, price) {
    window.dispatchEvent(new CustomEvent('open-buy-modal', { detail: { ticker, price } }));
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/dashboard/index.blade.php ENDPATH**/ ?>