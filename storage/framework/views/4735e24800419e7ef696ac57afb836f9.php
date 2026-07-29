<?php $__env->startSection('title', $user->name.' — Admin'); ?>

<?php $__env->startSection('content'); ?>
<a href="<?php echo e(route('admin.users.index')); ?>" class="text-sm text-slate-400 hover:text-slate-600 mb-4 inline-block"><i class="fa-solid fa-arrow-left mr-1"></i>Kembali</a>
<h1 class="text-xl font-extrabold mb-6"><?php echo e($user->name); ?></h1>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <h2 class="font-bold mb-4">Data Akun</h2>
        <form method="POST" action="<?php echo e(route('admin.users.update', $user)); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Nama</label>
                <input type="text" name="name" value="<?php echo e(old('name', $user->name)); ?>" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
                <input type="email" name="email" value="<?php echo e(old('email', $user->email)); ?>" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Password Baru (opsional)</label>
                <input type="password" name="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Kosongkan jika tidak diubah">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                <input type="checkbox" name="is_admin" value="1" <?php if($user->is_admin): echo 'checked'; endif; ?> class="rounded">
                Jadikan Administrator
            </label>

            <button type="submit" class="rounded-lg bg-primary text-white font-bold px-5 py-2 text-sm hover:bg-indigo-700">Simpan</button>
        </form>
    </div>

    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-5">
            <h2 class="font-bold mb-4">Aktivasi Langganan</h2>
            <form method="POST" action="<?php echo e(route('admin.users.subscription.activate', $user)); ?>" class="space-y-3">
                <?php echo csrf_field(); ?>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Paket</label>
                    <select name="subscription_plan_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <?php $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($plan->id); ?>"><?php echo e($plan->name); ?> — Rp <?php echo e(number_format((float) $plan->price_per_month, 0, ',', '.')); ?>/bln</option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Durasi (bulan)</label>
                    <input type="number" name="months" value="1" min="1" max="24" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="w-full rounded-lg bg-emerald-600 text-white font-bold py-2 text-sm hover:bg-emerald-700">
                    <i class="fa-solid fa-check mr-1"></i>Aktivasi
                </button>
            </form>

            <?php if($user->hasActiveSubscription()): ?>
                <form method="POST" action="<?php echo e(route('admin.users.subscription.cancel', $user)); ?>" class="mt-2" onsubmit="return confirm('Batalkan langganan aktif <?php echo e($user->name); ?>?')">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="w-full rounded-lg bg-red-50 text-red-600 font-bold py-2 text-sm hover:bg-red-100">Batalkan Langganan Aktif</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-sm">Riwayat Langganan</div>
            <div class="divide-y divide-slate-100">
                <?php $__empty_1 = true; $__currentLoopData = $subscriptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="px-4 py-3 flex justify-between items-center text-sm">
                        <div>
                            <div class="font-bold"><?php echo e($sub->plan->name); ?></div>
                            <div class="text-xs text-slate-400">
                                <?php echo e($sub->starts_at?->format('d M Y') ?? '-'); ?> – <?php echo e($sub->ends_at?->format('d M Y') ?? '-'); ?>

                            </div>
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full <?php echo e($sub->status->value === 'active' ? 'bg-emerald-100 text-emerald-700' : ($sub->status->value === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500')); ?>">
                            <?php echo e($sub->status->label()); ?>

                        </span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="px-4 py-6 text-center text-sm text-slate-400">Belum ada riwayat.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/admin/users/edit.blade.php ENDPATH**/ ?>