<?php $__env->startSection('title', 'Ghost Pattern — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-4">
    <h1 class="text-xl font-extrabold"><i class="fa-solid fa-ghost text-primary mr-2"></i>Ghost Hunter Pattern</h1>
    <a href="<?php echo e(route('dashboard')); ?>" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><i class="fa-solid fa-arrow-left mr-2"></i>Dashboard</a>
</div>

<form method="GET" class="mb-4 flex gap-2 max-w-sm">
    <input type="text" name="ticker" value="<?php echo e($ticker); ?>" placeholder="Kode saham, cth: BBCA" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold uppercase">
    <button type="submit" class="rounded-lg bg-primary text-white font-bold px-4 py-2 text-sm hover:bg-indigo-700 transition">Cari Pola</button>
</form>

<?php if(! $target): ?>
    <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl text-slate-400">Data historis <?php echo e($ticker); ?> tidak ditemukan.</div>
<?php elseif($matches->isEmpty()): ?>
    <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl">
        <i class="fa-solid fa-ghost fa-3x text-slate-200 mb-4"></i>
        <p class="text-slate-400">Tidak ditemukan pola yang mirip (korelasi &gt; 60%) untuk <?php echo e($ticker); ?>.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php $__currentLoopData = $matches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $t = str_replace('.JK', '', $match['price']->ticker); ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h4 class="font-extrabold"><?php echo e($t); ?></h4>
                        <div class="text-xs text-slate-400 truncate max-w-[9rem]"><?php echo e($match['price']->stockRef->nama_perusahaan ?? ''); ?></div>
                    </div>
                    <span class="text-xs font-extrabold bg-indigo-50 text-primary rounded-full px-2.5 py-1"><?php echo e($match['similarity']); ?>%</span>
                </div>
                <?php if (isset($component)) { $__componentOriginal298d4b9cf43466c1b93e4811c05e939c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal298d4b9cf43466c1b93e4811c05e939c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.sparkline','data' => ['history' => $match['price']->history_json ?? [],'width' => 150,'height' => 45,'color' => '#4f46e5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('sparkline'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['history' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($match['price']->history_json ?? []),'width' => 150,'height' => 45,'color' => '#4f46e5']); ?>
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
                <div class="font-bold mt-2">Rp <?php echo e(number_format($match['price']->close_price)); ?></div>
                <a href="<?php echo e(route('stocks.show', $t)); ?>" class="mt-3 block text-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-700 hover:text-white transition text-sm font-bold py-2">Lihat Detail</a>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/similarity/index.blade.php ENDPATH**/ ?>