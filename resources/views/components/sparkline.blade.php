@props(['history' => [], 'color' => '#10b981', 'width' => 80, 'height' => 25])

@php
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
@endphp

@if ($points !== '')
    <svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}">
        <polyline points="{{ trim($points) }}" fill="none" stroke="{{ $color }}" stroke-width="1.5" />
    </svg>
@endif
