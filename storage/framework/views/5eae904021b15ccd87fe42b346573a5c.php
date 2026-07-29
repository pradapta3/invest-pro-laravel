<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['href', 'active' => false, 'icon' => null, 'variant' => 'default']));

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

foreach (array_filter((['href', 'active' => false, 'icon' => null, 'variant' => 'default']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $activeClasses = match ($variant) {
        'purple' => 'bg-purple-600 text-white border-purple-600',
        'gold' => 'bg-amber-500 text-white border-amber-500',
        default => 'bg-slate-900 text-white border-slate-900',
    };
    $classes = 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold transition '
        .($active ? $activeClasses : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300');
?>

<a href="<?php echo e($href); ?>" class="<?php echo e($classes); ?>">
    <?php if($icon): ?>
        <i class="fa-solid <?php echo e($icon); ?>"></i>
    <?php endif; ?>
    <?php echo e($slot); ?>

</a>
<?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/components/filter-chip.blade.php ENDPATH**/ ?>