@props(['score'])

@php($colour = \App\Support\Format::scoreBadgeClass((int) $score))

{{-- data-score-value marks the text the live poller replaces; the colour comes
     back from the same endpoint so it cannot drift from the number, and
     data-swap-class records which classes are currently the colour so the
     poller can take them off again without knowing the palette. --}}
<div {{ $attributes->merge(['class' => 'w-9 h-9 rounded-lg flex items-center justify-center font-extrabold text-sm '.$colour]) }}
     data-score-badge
     data-swap-class="{{ $colour }}">
    <span data-score-value>{{ $score }}</span>
</div>
