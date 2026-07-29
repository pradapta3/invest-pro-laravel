<?php $__env->startSection('title', 'Quant Scan — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-xl font-extrabold"><i class="fa-solid fa-brain text-primary mr-2"></i>Quant Scan</h1>
        <p class="text-sm text-slate-400">Math Regression + Gemini 2.0</p>
    </div>
    <a href="<?php echo e(route('dashboard')); ?>" class="text-sm font-bold border border-slate-200 rounded-lg px-3 py-1.5 hover:bg-slate-50">Dashboard</a>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden" x-data="{ tab: 'magic' }">
    <div class="p-3 border-b border-slate-100 overflow-x-auto">
        <div class="flex gap-2 flex-nowrap">
            <?php $__currentLoopData = ['magic' => '🧙 Magic', 'bandar' => '🐳 Bandar', 'trend' => '🚀 Trend', 'pullback' => '🎯 Sniper', 'value' => '💎 Value']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button @click="tab = '<?php echo e($key); ?>'" :class="tab === '<?php echo e($key); ?>' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200'" class="rounded-full px-4 py-1.5 text-sm font-bold whitespace-nowrap transition"><?php echo e($label); ?></button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <div x-show="tab === 'magic'"><?php echo $__env->make('partials.screener-table', ['rows' => $magic, 'type' => 'magic'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
    <div x-show="tab === 'bandar'" x-cloak><?php echo $__env->make('partials.screener-table', ['rows' => $bandar, 'type' => 'bandar'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
    <div x-show="tab === 'trend'" x-cloak><?php echo $__env->make('partials.screener-table', ['rows' => $trend, 'type' => 'trend'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
    <div x-show="tab === 'pullback'" x-cloak><?php echo $__env->make('partials.screener-table', ['rows' => $pullback, 'type' => 'pullback'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
    <div x-show="tab === 'value'" x-cloak><?php echo $__env->make('partials.screener-table', ['rows' => $value, 'type' => 'value'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/scanner/quant.blade.php ENDPATH**/ ?>