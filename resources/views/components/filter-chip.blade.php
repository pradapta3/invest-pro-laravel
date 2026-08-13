@props(['href', 'active' => false, 'icon' => null, 'variant' => 'default'])

@php
    $activeClasses = match ($variant) {
        'purple' => 'bg-purple-600 text-white border-purple-600',
        'gold' => 'bg-amber-500 text-white border-amber-500',
        default => 'bg-slate-900 text-white border-slate-900',
    };
    $classes = 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-3.5 py-1.5 text-xs font-bold transition '
        .($active ? $activeClasses : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300');
@endphp

<a href="{{ $href }}" class="{{ $classes }}">
    @if ($icon)
        <x-icon :name="$icon" class="w-3.5 h-3.5" :solid="true" />
    @endif
    {{ $slot }}
</a>
