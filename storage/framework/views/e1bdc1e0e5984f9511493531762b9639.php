<?php $__env->startSection('title', 'News — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-4">
    <h1 class="text-xl font-extrabold"><i class="fa-solid fa-newspaper text-red-500 mr-2"></i>Market News</h1>
    <a href="<?php echo e(route('dashboard')); ?>" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><i class="fa-solid fa-arrow-left mr-2"></i>Dashboard</a>
</div>

<?php if(empty($articles)): ?>
    <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl text-slate-400">Gagal memuat berita. Coba lagi nanti.</div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php $__currentLoopData = $articles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $article): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                // Literal Tailwind class strings on purpose (not string-built),
                // so the JIT content scanner can actually find them.
                $badgeClass = match ($article['color']) {
                    'success' => 'text-emerald-600 bg-emerald-50',
                    'info' => 'text-sky-600 bg-sky-50',
                    default => 'text-primary bg-indigo-50',
                };
            ?>
            <a href="<?php echo e($article['link']); ?>" target="_blank" rel="noopener noreferrer" class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:shadow-lg transition block">
                <img src="<?php echo e($article['img']); ?>" alt="" class="w-full h-40 object-cover bg-slate-100" loading="lazy">
                <div class="p-4">
                    <span class="text-[10px] font-bold uppercase rounded px-2 py-0.5 <?php echo e($badgeClass); ?>"><?php echo e($article['source']); ?></span>
                    <h3 class="font-bold text-sm mt-2 mb-1 line-clamp-2"><?php echo e($article['title']); ?></h3>
                    <p class="text-xs text-slate-400 line-clamp-2 mb-2"><?php echo e($article['desc']); ?></p>
                    <div class="text-[10px] text-slate-300"><?php echo e(\Illuminate\Support\Carbon::createFromTimestamp($article['date'])->diffForHumans()); ?></div>
                </div>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/news/index.blade.php ENDPATH**/ ?>