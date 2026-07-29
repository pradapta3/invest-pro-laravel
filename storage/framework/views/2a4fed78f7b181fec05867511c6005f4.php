<?php $__env->startSection('title', 'Titan Radar — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-6">
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-extrabold bg-gradient-to-r from-amber-500 to-amber-600 bg-clip-text text-transparent"><i class="fa-solid fa-bolt mr-2"></i>TITAN RADAR</h1>
        <span class="text-xs font-bold bg-slate-900/10 text-slate-700 rounded-full px-3 py-1"><?php echo e($signals->count()); ?> Detected</span>
    </div>
    <a href="<?php echo e(route('dashboard')); ?>" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><i class="fa-solid fa-arrow-left mr-2"></i>Dashboard</a>
</div>

<?php if($signals->isEmpty()): ?>
    <div class="text-center py-16">
        <i class="fa-solid fa-satellite-dish fa-3x text-slate-200 mb-4"></i>
        <h4 class="text-slate-400 font-bold">No Titan Activity</h4>
        <p class="text-slate-300 text-sm">Pasar sedang sepi. Tunggu ledakan volume berikutnya.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php $__currentLoopData = $signals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $t = str_replace('.JK', '', $signal->price->ticker);
                $tierClasses = match ($signal->tier) {
                    'S' => 'bg-amber-100 text-amber-600 border-amber-300',
                    'A' => 'bg-purple-100 text-purple-600 border-purple-300',
                    default => 'bg-sky-100 text-sky-600 border-sky-300',
                };
            ?>
            <div class="relative bg-white border border-slate-200 rounded-2xl p-4 hover:shadow-lg hover:-translate-y-1 transition">
                <div class="absolute top-3 right-3 w-8 h-8 rounded-lg flex items-center justify-center font-extrabold text-sm border <?php echo e($tierClasses); ?>"><?php echo e($signal->tier); ?></div>

                <h4 class="font-extrabold text-lg"><?php echo e($t); ?></h4>
                <div class="text-xs text-slate-400 truncate max-w-[10rem]"><?php echo e($signal->price->stockRef->nama_perusahaan ?? ''); ?></div>
                <div class="text-xl font-extrabold text-emerald-600 mt-2">Rp <?php echo e(number_format($signal->price->close_price)); ?></div>

                <div class="grid grid-cols-3 gap-2 my-3 text-center text-xs">
                    <div class="border-r border-slate-100"><div class="text-slate-400">VOL SPIKE</div><div class="font-bold text-amber-600"><?php echo e(round($signal->volumeSpikeRatio, 1)); ?>x</div></div>
                    <div class="border-r border-slate-100"><div class="text-slate-400">VALUE</div><div class="font-bold"><?php echo e(number_format((float) $signal->price->value_transaction / 1_000_000_000, 1)); ?>M</div></div>
                    <div><div class="text-slate-400">POWER</div><div class="font-bold text-primary"><?php echo e($signal->score); ?></div></div>
                </div>

                <div class="flex flex-wrap gap-1 mb-3">
                    <?php $__currentLoopData = $signal->tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded <?php echo e(str_contains($tag, 'VOL') ? 'bg-orange-50 text-orange-600' : 'bg-emerald-50 text-emerald-600'); ?>"><?php echo e($tag); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                <div class="bg-slate-50 border border-dashed border-slate-300 rounded-lg p-2.5 text-xs mb-3 space-y-1">
                    <div class="flex justify-between"><span class="text-slate-500">Target 1</span><span class="font-bold text-emerald-600"><?php echo e(number_format($signal->plan->takeProfit)); ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Target 2</span><span class="font-bold text-emerald-600"><?php echo e(number_format((float) $signal->plan->takeProfit2)); ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Stop Loss</span><span class="font-bold text-red-600"><?php echo e(number_format($signal->plan->stopLoss)); ?></span></div>
                </div>

                <div class="flex gap-2">
                    <button onclick="sendSignal('<?php echo e($t); ?>', 'titan')" class="w-9 h-9 shrink-0 rounded-lg bg-sky-100 text-sky-600 hover:bg-sky-600 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-paper-plane"></i></button>
                    <a href="<?php echo e(route('stocks.show', $t)); ?>" class="flex-1 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-700 hover:text-white transition text-sm font-bold flex items-center justify-center gap-1">Check Chart <i class="fa-solid fa-arrow-right text-xs"></i></a>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/scanner/titan.blade.php ENDPATH**/ ?>