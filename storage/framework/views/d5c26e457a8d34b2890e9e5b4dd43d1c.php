<?php $__env->startSection('title', 'Daftar — IDX Invest'); ?>

<?php ($wide = true); ?>

<?php $__env->startSection('content'); ?>
<h1 class="text-lg font-extrabold mb-1">Buat Akun</h1>
<p class="text-sm text-slate-400 mb-6">Pilih paket langganan dan lengkapi data Anda.</p>

<form method="POST" action="<?php echo e(route('register')); ?>" class="space-y-5" x-data="{ plan: '<?php echo e(old('subscription_plan_id', $plans->first()?->id)); ?>' }">
    <?php echo csrf_field(); ?>

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-2">Pilih Paket</label>
        <div class="grid grid-cols-1 sm:grid-cols-<?php echo e(min(3, max(1, $plans->count()))); ?> gap-3">
            <?php $__empty_1 = true; $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <label class="relative block rounded-xl border-2 p-4 cursor-pointer transition"
                       :class="plan == '<?php echo e($plan->id); ?>' ? 'border-primary bg-indigo-50' : 'border-slate-200 hover:border-slate-300'">
                    <input type="radio" name="subscription_plan_id" value="<?php echo e($plan->id); ?>" x-model="plan" class="sr-only"
                           <?php if(old('subscription_plan_id', $plans->first()?->id) == $plan->id): echo 'checked'; endif; ?>>
                    <div class="font-extrabold"><?php echo e($plan->name); ?></div>
                    <div class="text-xl font-extrabold text-primary mt-1">
                        Rp <?php echo e(number_format((float) $plan->price_per_month, 0, ',', '.')); ?>

                        <span class="text-xs font-semibold text-slate-400">/bulan</span>
                    </div>
                    <?php if($plan->description): ?>
                        <p class="text-xs text-slate-500 mt-2"><?php echo e($plan->description); ?></p>
                    <?php endif; ?>
                    <?php if(is_array($plan->features)): ?>
                        <ul class="text-xs text-slate-500 mt-2 space-y-1">
                            <?php $__currentLoopData = $plan->features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><i class="fa-solid fa-check text-emerald-500 mr-1"></i><?php echo e($feature); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php endif; ?>
                </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="text-sm text-red-600 col-span-full">Belum ada paket langganan tersedia. Hubungi admin.</p>
            <?php endif; ?>
        </div>
        <?php $__errorArgs = ['subscription_plan_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-xs text-red-600 mt-2 font-semibold"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Nama Lengkap</label>
            <input type="text" name="name" value="<?php echo e(old('name')); ?>" required autofocus
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-xs text-red-600 mt-1 font-semibold"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
            <input type="email" name="email" value="<?php echo e(old('email')); ?>" required autocomplete="username"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-xs text-red-600 mt-1 font-semibold"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Password</label>
            <input type="password" name="password" required autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-xs text-red-600 mt-1 font-semibold"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
        Setelah daftar, akun Anda berstatus <b>Menunggu Aktivasi</b> sampai admin mengonfirmasi pembayaran langganan.
    </div>

    <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2.5 text-sm hover:bg-indigo-700 transition">Daftar</button>
</form>

<p class="text-center text-sm text-slate-400 mt-6">
    Sudah punya akun? <a href="<?php echo e(route('login')); ?>" class="text-primary font-bold hover:underline">Masuk</a>
</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/auth/register.blade.php ENDPATH**/ ?>