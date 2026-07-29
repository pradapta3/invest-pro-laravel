@props(['score'])

@php
    $bg = $score >= 75 ? 'bg-emerald-100 text-emerald-700' : ($score < 40 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
@endphp

<div {{ $attributes->merge(['class' => "w-9 h-9 rounded-lg flex items-center justify-center font-extrabold text-sm $bg"]) }}>
    {{ $score }}
</div>
