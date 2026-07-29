<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['score']));

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

foreach (array_filter((['score']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $bg = $score >= 75 ? 'bg-emerald-100 text-emerald-700' : ($score < 40 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
?>

<div <?php echo e($attributes->merge(['class' => "w-9 h-9 rounded-lg flex items-center justify-center font-extrabold text-sm $bg"])); ?>>
    <?php echo e($score); ?>

</div>
<?php /**PATH C:\xampp\htdocs\saham-bot-laravel\resources\views/components/score-badge.blade.php ENDPATH**/ ?>