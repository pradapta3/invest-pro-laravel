<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $__env->yieldContent('title', 'Admin — IDX Invest'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Roboto+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if(file_exists(public_path('build/manifest.json'))): ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php else: ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { theme: { extend: { colors: { primary: '#4f46e5' }, fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } };
        </script>
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans">

<div class="flex min-h-screen">
    <aside class="w-60 bg-slate-900 text-slate-300 flex-shrink-0 flex flex-col">
        <div class="p-5 border-b border-slate-800">
            <span class="text-lg font-extrabold text-white">DOMPET IJO</span>
            <div class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mt-0.5">Admin Panel</div>
        </div>
        <nav class="flex-1 p-3 space-y-1 text-sm font-semibold">
            <a href="<?php echo e(route('admin.dashboard')); ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg <?php echo e(request()->routeIs('admin.dashboard') ? 'bg-primary text-white' : 'hover:bg-slate-800'); ?>">
                <i class="fa-solid fa-chart-pie w-4"></i> Dashboard
            </a>
            <a href="<?php echo e(route('admin.users.index')); ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg <?php echo e(request()->routeIs('admin.users.*') ? 'bg-primary text-white' : 'hover:bg-slate-800'); ?>">
                <i class="fa-solid fa-users w-4"></i> Pengguna
            </a>
            <a href="<?php echo e(route('admin.plans.index')); ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg <?php echo e(request()->routeIs('admin.plans.*') ? 'bg-primary text-white' : 'hover:bg-slate-800'); ?>">
                <i class="fa-solid fa-tags w-4"></i> Paket Langganan
            </a>
        </nav>
        <div class="p-3 border-t border-slate-800 space-y-1">
            <a href="<?php echo e(route('dashboard')); ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold hover:bg-slate-800">
                <i class="fa-solid fa-arrow-left w-4"></i> Ke Aplikasi
            </a>
            <form method="POST" action="<?php echo e(route('logout')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold hover:bg-slate-800 text-left">
                    <i class="fa-solid fa-right-from-bracket w-4"></i> Keluar
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 p-6 md:p-8 max-w-6xl">
        <?php if(session('status')): ?>
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 font-semibold">
                <?php echo e(session('status')); ?>

            </div>
        <?php endif; ?>
        <?php if($errors->any()): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 font-semibold">
                <ul class="list-disc pl-4">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php echo $__env->yieldContent('content'); ?>
    </main>
</div>

</body>
</html>
<?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/layouts/admin.blade.php ENDPATH**/ ?>