<?php $__env->startSection('title', 'Menunggu Aktivasi — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<div class="text-center">
    <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-500 flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-hourglass-half text-2xl"></i>
    </div>

    <?php if($subscription === null): ?>
        <h1 class="text-lg font-extrabold mb-2">Belum Ada Langganan</h1>
        <p class="text-sm text-slate-500">Akun Anda belum memiliki paket langganan. Hubungi admin untuk mengaktifkan akses.</p>
    <?php else: ?>
        <h1 class="text-lg font-extrabold mb-2">Status: <?php echo e($subscription->status->label()); ?></h1>
        <p class="text-sm text-slate-500 mb-4">
            Paket <b><?php echo e($subscription->plan->name); ?></b>
            (Rp <?php echo e(number_format((float) $subscription->plan->price_per_month, 0, ',', '.')); ?>/bulan)
            sedang menunggu aktivasi oleh admin. Anda akan bisa mengakses aplikasi setelah pembayaran dikonfirmasi.
        </p>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('logout')); ?>" class="mt-4">
        <?php echo csrf_field(); ?>
        <button type="submit" class="text-sm font-bold text-slate-400 hover:text-slate-600">
            <i class="fa-solid fa-right-from-bracket mr-1"></i>Keluar
        </button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/subscription/pending.blade.php ENDPATH**/ ?>