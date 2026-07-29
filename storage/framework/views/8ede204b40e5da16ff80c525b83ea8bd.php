<?php $__env->startSection('title', 'Seasonal Compass — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-4">
    <h1 class="text-xl font-extrabold"><i class="fa-regular fa-calendar-check text-primary mr-2"></i>Seasonal Compass</h1>
    <a href="<?php echo e(route('dashboard')); ?>" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><i class="fa-solid fa-arrow-left mr-2"></i>Dashboard</a>
</div>

<form method="GET" class="mb-4 flex gap-2 max-w-sm">
    <input type="text" name="ticker" value="<?php echo e($ticker); ?>" placeholder="Kode saham, cth: BBCA" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold uppercase">
    <button type="submit" class="rounded-lg bg-primary text-white font-bold px-4 py-2 text-sm hover:bg-indigo-700 transition">Cari</button>
</form>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
        <h2 class="font-bold"><?php echo e($ticker); ?> <span class="text-sm font-normal text-slate-400"><?php echo e($ref?->nama_perusahaan); ?></span></h2>
        <?php if(!empty($monthlyByYear)): ?>
            <span class="text-xs font-bold bg-white border border-slate-200 rounded-full px-3 py-1">
                Best: <b class="text-emerald-600"><?php echo e(\Carbon\Carbon::create()->month($seasonality['best']['month'])->translatedFormat('M')); ?></b>
                · Worst: <b class="text-red-600"><?php echo e(\Carbon\Carbon::create()->month($seasonality['worst']['month'])->translatedFormat('M')); ?></b>
            </span>
        <?php endif; ?>
    </div>
    <div class="p-4">
        <?php echo $__env->make('partials.seasonality-table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/seasonality/index.blade.php ENDPATH**/ ?>