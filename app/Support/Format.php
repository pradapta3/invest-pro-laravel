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
     * A ratio with enough decimals to still be a number.
     *
     * number_format($v, 2) prints 0.0042 as "0.00", which is the same
     * indistinguishable-from-missing zero that the decimal column type and the
     * decimal cast were each producing at their own layer. Small values get
     * more places so a genuinely tiny price-to-book reads as tiny rather than
     * as absent, and ordinary ones keep the two everyone expects.
     */
    public static function ratio(?float $value, string $suffix = 'x'): string
    {
        if ($value === null) {
            return '-';
        }

        $magnitude = abs($value);

        $decimals = match (true) {
            $magnitude === 0.0 => 2,
            $magnitude < 0.01 => 4,
            $magnitude < 1 => 3,
            default => 2,
        };

        return number_format($value, $decimals).$suffix;
    }

    /** A percentage, with the same protection against rounding to nothing. */
    public static function percent(?float $value): string
    {
        return $value === null ? '-' : self::ratio($value, '%');
    }

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
