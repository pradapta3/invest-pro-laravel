<?php

namespace App\Support;

/**
 * Small presentation-only number formatters used across the Blade views,
 * replacing the formatCap() copy duplicated in functions.php, index.php
 * and detail.php.
 */
final class Format
{
    /**
     * Large Rupiah amounts abbreviated to Triliun/Miliar, e.g. 1.2T, 850M.
     */
    public static function compactRupiah(float|string $value): string
    {
        $value = (float) $value;

        if ($value >= 1_000_000_000_000) {
            return round($value / 1_000_000_000_000, 1).'T';
        }

        if ($value >= 1_000_000_000) {
            return round($value / 1_000_000_000, 1).'M';
        }

        return $value > 0 ? number_format($value) : '-';
    }
}
