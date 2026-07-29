<?php $__env->startSection('title', 'Paket Langganan — Admin'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-extrabold">Paket Langganan</h1>
    <a href="<?php echo e(route('admin.plans.create')); ?>" class="rounded-lg bg-primary text-white font-bold px-4 py-2 text-sm hover:bg-indigo-700">
        <i class="fa-solid fa-plus mr-1"></i>Paket Baru
    </a>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-400">
            <tr>
                <th class="text-left px-4 py-3">Nama</th>
                <th class="text-left py-3">Harga/Bulan</th>
                <th class="text-left py-3">Pelanggan</th>
                <th class="text-left py-3">Status</th>
                <th class="text-right px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-bold"><?php echo e($plan->name); ?></td>
                    <td class="py-3">Rp <?php echo e(number_format((float) $plan->price_per_month, 0, ',', '.')); ?></td>
                    <td class="py-3"><?php echo e($plan->subscriptions_count); ?></td>
                    <td class="py-3">
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full <?php echo e($plan->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'); ?>">
                            <?php echo e($plan->is_active ? 'Aktif' : 'Nonaktif'); ?>

                        </span>
                    </td>
                    <td class="text-right px-4 py-3 space-x-2">
                        <a href="<?php echo e(route('admin.plans.edit', $plan)); ?>" class="text-xs font-bold text-primary hover:underline">Edit</a>
                        <form method="POST" action="<?php echo e(route('admin.plans.destroy', $plan)); ?>" class="inline" onsubmit="return confirm('Hapus paket <?php echo e($plan->name); ?>?')">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-xs font-bold text-red-500 hover:underline">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/admin/plans/index.blade.php ENDPATH**/ ?>