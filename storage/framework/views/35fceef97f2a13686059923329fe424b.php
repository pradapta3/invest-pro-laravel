<?php $__env->startSection('title', 'Admin Dashboard — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="text-xl font-extrabold mb-6">Dashboard Admin</h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs font-bold text-slate-400 uppercase">Total Pengguna</div>
        <div class="text-2xl font-extrabold"><?php echo e($totalUsers); ?></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs font-bold text-slate-400 uppercase">Langganan Aktif</div>
        <div class="text-2xl font-extrabold text-emerald-600"><?php echo e($activeCount); ?></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs font-bold text-slate-400 uppercase">Menunggu Aktivasi</div>
        <div class="text-2xl font-extrabold text-amber-500"><?php echo e($pendingCount); ?></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs font-bold text-slate-400 uppercase">Estimasi MRR</div>
        <div class="text-xl font-extrabold">Rp <?php echo e(number_format($estimatedMrr, 0, ',', '.')); ?></div>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h2 class="font-bold text-sm">Menunggu Aktivasi</h2>
            <a href="<?php echo e(route('admin.users.index')); ?>" class="text-xs font-bold text-primary hover:underline">Lihat semua</a>
        </div>
        <div class="divide-y divide-slate-100">
            <?php $__empty_1 = true; $__currentLoopData = $pendingSubscriptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="px-4 py-3 flex justify-between items-center">
                    <div>
                        <div class="font-bold text-sm"><?php echo e($sub->user->name); ?></div>
                        <div class="text-xs text-slate-400"><?php echo e($sub->plan->name); ?> — Rp <?php echo e(number_format((float) $sub->plan->price_per_month, 0, ',', '.')); ?>/bln</div>
                    </div>
                    <a href="<?php echo e(route('admin.users.edit', $sub->user)); ?>" class="text-xs font-bold bg-primary text-white rounded-lg px-3 py-1.5 hover:bg-indigo-700">Aktivasi</a>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="px-4 py-6 text-center text-sm text-slate-400">Tidak ada yang menunggu.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100">
            <h2 class="font-bold text-sm">Pendaftar Terbaru</h2>
        </div>
        <div class="divide-y divide-slate-100">
            <?php $__currentLoopData = $recentUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="px-4 py-3 flex justify-between items-center">
                    <div>
                        <div class="font-bold text-sm"><?php echo e($u->name); ?></div>
                        <div class="text-xs text-slate-400"><?php echo e($u->email); ?></div>
                    </div>
                    <span class="text-xs text-slate-400"><?php echo e($u->created_at->diffForHumans()); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>