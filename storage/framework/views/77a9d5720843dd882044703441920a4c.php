<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['history' => [], 'color' => '#10b981', 'width' => 80, 'height' => 25]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['history' => [], 'color' => '#10b981', 'width' => 80, 'height' => 25]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $data = array_values(array_filter((array) $history, fn ($v) => is_numeric($v)));
    $points = '';

    if (count($data) >= 2) {
        $min = min($data);
        $max = max($data);
        $range = $max - $min ?: 1;
        $step = $width / (count($data) - 1);

        foreach ($data as $i => $v) {
            $x = $i * $step;
            $y = $height - (($v - $min) / $range * $height);
            $points .= "{$x},{$y} ";
        }
    }
?>

<?php if($points !== ''): ?>
    <svg width="<?php echo e($width); ?>" height="<?php echo e($height); ?>" viewBox="0 0 <?php echo e($width); ?> <?php echo e($height); ?>">
        <polyline points="<?php echo e(trim($points)); ?>" fill="none" stroke="<?php echo e($color); ?>" stroke-width="1.5" />
    </svg>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/components/sparkline.blade.php ENDPATH**/ ?>