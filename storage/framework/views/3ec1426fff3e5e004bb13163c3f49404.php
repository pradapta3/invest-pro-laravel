
<?php
    $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $years = range(now()->year - 4, now()->year);
    $stats = $seasonality['stats'];
    $maxAbs = max(1, collect($stats)->max(fn ($s) => abs($s['avg'])));

    $heatClass = function (float $val) {
        if ($val >= 10) return 'bg-emerald-800 text-white';
        if ($val >= 5) return 'bg-emerald-500 text-white';
        if ($val > 0) return 'bg-emerald-100 text-emerald-900';
        if ($val <= -10) return 'bg-red-800 text-white';
        if ($val <= -5) return 'bg-red-500 text-white';
        if ($val < 0) return 'bg-red-100 text-red-900';
        return 'bg-slate-50 text-slate-400';
    };
?>

<?php if(empty($monthlyByYear)): ?>
    <div class="text-center py-8 text-sm text-slate-400">Data historis tidak cukup.</div>
<?php else: ?>
    <div class="flex items-end gap-1 h-24 mb-4 border-b border-slate-200 pb-0">
        <?php $__currentLoopData = $months; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $val = $stats[$num]['avg'];
                $h = (abs($val) / $maxAbs) * 100;
            ?>
            <div class="flex-1 flex flex-col items-center justify-end h-full">
                <span class="text-[10px] font-bold <?php echo e($val >= 0 ? 'text-emerald-600' : 'text-red-600'); ?>"><?php echo e(round($val, 1)); ?>%</span>
                <div class="w-full rounded-t <?php echo e($val >= 0 ? 'bg-emerald-500' : 'bg-red-500'); ?>" style="height: <?php echo e(max(2, $h)); ?>%"></div>
                <span class="text-[10px] text-slate-400 mt-1"><?php echo e(substr($name, 0, 1)); ?></span>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-center text-xs">
            <thead class="text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="text-left py-1">Bulan</th>
                    <th class="py-1">Win%</th>
                    <?php $__currentLoopData = $years; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $y): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <th class="py-1"><?php echo e($y); ?></th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $months; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $wr = $stats[$num]['win_rate']; ?>
                    <tr>
                        <td class="text-left font-bold py-1"><?php echo e($name); ?></td>
                        <td class="py-1 font-bold <?php echo e($wr >= 80 ? 'text-emerald-600' : ($wr <= 20 ? 'text-red-600' : 'text-slate-400')); ?>"><?php echo e(round($wr)); ?>%</td>
                        <?php $__currentLoopData = $years; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $y): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $val = $monthlyByYear[$num][$y] ?? 0; ?>
                            <td class="py-1.5 font-bold border border-white <?php echo e($heatClass($val)); ?>"><?php echo e($val != 0 ? round($val, 1).'%' : '-'); ?></td>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/partials/seasonality-table.blade.php ENDPATH**/ ?>