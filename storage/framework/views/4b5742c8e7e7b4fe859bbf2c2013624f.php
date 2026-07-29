<?php $__env->startSection('title', 'Login — IDX Invest'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="text-lg font-extrabold mb-1">Masuk</h1>
<p class="text-sm text-slate-400 mb-6">Selamat datang kembali.</p>

<form method="POST" action="<?php echo e(route('login')); ?>" class="space-y-4">
    <?php echo csrf_field(); ?>

    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
        <input type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus autocomplete="username"
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
        <input type="password" name="password" required autocomplete="current-password"
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

    <div class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2 text-slate-500">
            <input type="checkbox" name="remember" class="rounded">
            Ingat saya
        </label>
        <a href="<?php echo e(route('password.request')); ?>" class="text-primary font-bold hover:underline">Lupa password?</a>
    </div>

    <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2.5 text-sm hover:bg-indigo-700 transition">Masuk</button>
</form>

<p class="text-center text-sm text-slate-400 mt-6">
    Belum punya akun? <a href="<?php echo e(route('register')); ?>" class="text-primary font-bold hover:underline">Daftar</a>
</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/auth/login.blade.php ENDPATH**/ ?>