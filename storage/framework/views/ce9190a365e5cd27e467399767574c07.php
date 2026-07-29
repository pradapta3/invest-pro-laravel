<?php $__env->startSection('title', 'Backtest — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $strategyLabels = [
        'bsjp' => 'BSJP (Beli Sore Jual Pagi)',
        'titan' => 'Titan Volatility',
        'trend_runner' => 'Trend Runner',
        'pullback_sniper' => 'Pullback Sniper',
        'bandar_radar' => 'Bandar Radar',
    ];
?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-xl font-extrabold"><i class="fa-solid fa-flask text-emerald-600 mr-2"></i>Backtest</h1>
        <p class="text-sm text-slate-400">Uji strategi terhadap data historis asli, bukan asumsi.</p>
    </div>
    <a href="<?php echo e(route('dashboard')); ?>" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><i class="fa-solid fa-arrow-left mr-2"></i>Dashboard</a>
</div>

<div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-4 text-sm text-amber-800">
    <b>Yang perlu diketahui:</b> Magic Formula, Undervalued, Top Picks dan Value Investing tidak bisa di-backtest di sini —
    strategi itu bergantung pada ROE/PER/PBV/DER yang hanya tersimpan snapshot hari ini, bukan riwayat historis
    (kalau dipaksa, hasilnya memakai data fundamental hari ini untuk tanggal masa lalu — <i>look-ahead bias</i>, bikin
    hasil terlihat bagus secara palsu). Angka di bawah juga mengasumsikan setiap sinyal dapat modal yang sama besar
    (bukan simulasi portofolio dengan modal terbatas) — lihat sebagai "apakah aturan ini layak diikuti", bukan
    "segini pasti untungnya".
</div>

<div class="bg-white border border-slate-200 rounded-2xl p-4 mb-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Strategi</label>
            <select name="strategy" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold">
                <?php $__currentLoopData = $strategies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($key); ?>" <?php if($strategy === $key): echo 'selected'; endif; ?>><?php echo e($strategyLabels[$key] ?? $key); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Dari</label>
            <input type="date" name="from" value="<?php echo e($from); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Sampai</label>
            <input type="date" name="to" value="<?php echo e($to); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <label class="flex items-center gap-2 text-sm font-bold text-slate-600 pb-2">
            <input type="checkbox" name="walk_forward" value="1" <?php if($walkForward): echo 'checked'; endif; ?> class="rounded">
            Walk-forward (per tahun)
        </label>
        <button type="submit" class="rounded-lg bg-primary text-white font-bold px-5 py-2 text-sm hover:bg-indigo-700 transition">Jalankan</button>
    </form>
</div>

<?php if($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 text-sm text-red-700 font-semibold"><?php echo e($error); ?></div>
<?php elseif($walkForwardReport): ?>
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-4">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h2 class="font-bold">Per Periode</h2>
            <span class="text-xs font-bold px-3 py-1 rounded-full <?php echo e($walkForwardReport->isConsistent() ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'); ?>">
                <?php echo e($walkForwardReport->isConsistent() ? 'Konsisten antar periode' : 'TIDAK konsisten — hati-hati'); ?>

            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                    <tr><th class="text-left px-4 py-2">Periode</th><th class="text-right py-2">Trades</th><th class="text-right py-2">Win Rate</th><th class="text-right py-2">Total Return</th><th class="text-right py-2">Max DD</th><th class="text-right px-4 py-2">Sharpe</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php $__currentLoopData = $walkForwardReport->periods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="px-4 py-2 font-bold"><?php echo e($period->periodStart->format('d M Y')); ?> – <?php echo e($period->periodEnd->format('d M Y')); ?></td>
                            <td class="text-right py-2"><?php echo e($period->tradeCount()); ?></td>
                            <td class="text-right py-2"><?php echo e(round($period->winRate(), 1)); ?>%</td>
                            <td class="text-right py-2 font-bold <?php echo e($period->totalReturnPct() >= 0 ? 'text-emerald-600' : 'text-red-600'); ?>"><?php echo e(round($period->totalReturnPct(), 2)); ?>%</td>
                            <td class="text-right py-2 text-red-600"><?php echo e(round($period->maxDrawdownPct(), 2)); ?>%</td>
                            <td class="text-right px-4 py-2"><?php echo e($period->sharpeRatio() !== null ? round($period->sharpeRatio(), 2) : '-'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $result = $walkForwardReport->aggregate; ?>
    <div class="mb-2 text-xs font-bold text-slate-400 uppercase">Agregat (seluruh periode)</div>
    <?php echo $__env->make('backtest.partials.result-summary', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php elseif($result): ?>
    <?php echo $__env->make('backtest.partials.result-summary', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<?php if($result): ?>
<script>
const equity = <?php echo json_encode($result->equityCurve(), 15, 512) ?>;
if (equity.length > 0) {
    const ctx = document.getElementById('equityChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: equity.map(p => p.date),
            datasets: [{ label: 'Cumulative Return %', data: equity.map(p => p.cumulative_return_pct), borderColor: '#4f46e5', borderWidth: 2, tension: 0.2, pointRadius: 0, fill: false }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } }, y: { ticks: { callback: (v) => v + '%' } } },
        },
    });
}
</script>
<?php endif; ?>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/backtest/index.blade.php ENDPATH**/ ?>