<?php $__env->startSection('title', 'Pengguna — Admin'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-extrabold">Pengguna</h1>
    <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Cari nama/email..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-56">
        <button class="rounded-lg bg-slate-800 text-white text-sm font-bold px-4 py-2">Cari</button>
    </form>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                <tr>
                    <th class="text-left px-4 py-3">Nama</th>
                    <th class="text-left py-3">Email</th>
                    <th class="text-left py-3">Role</th>
                    <th class="text-left py-3">Langganan</th>
                    <th class="text-left py-3">Berakhir</th>
                    <th class="text-right px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php($sub = $u->currentSub)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-bold">{{ $u->name }}</td>
                        <td class="py-3 text-slate-500">{{ $u->email }}</td>
                        <td class="py-3">
                            @if ($u->is_admin)
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-800 text-white">Admin</span>
                            @else
                                <span class="text-xs text-slate-400">Pengguna</span>
                            @endif
                        </td>
                        <td class="py-3">
                            @if ($sub === null)
                                <span class="text-xs text-slate-400">Belum ada</span>
                            @else
                                @php
                                    $statusColor = match($sub->status->value) {
                                        'active' => 'bg-emerald-100 text-emerald-700',
                                        'pending' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-100 text-slate-500',
                                    };
                                ?>
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full <?php echo e($statusColor); ?>"><?php echo e($sub->plan->name); ?> — <?php echo e($sub->status->label()); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-slate-500"><?php echo e($sub?->ends_at?->format('d M Y') ?? '-'); ?></td>
                        <td class="text-right px-4 py-3">
                            <a href="<?php echo e(route('admin.users.edit', $u)); ?>" class="text-xs font-bold text-primary hover:underline">Kelola</a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-slate-100"><?php echo e($users->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/admin/users/index.blade.php ENDPATH**/ ?>