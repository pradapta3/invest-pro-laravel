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
     * Tailwind classes for a 0-100 AI score badge.
     *
     * Here rather than inline in the Blade component because the live-quote
     * endpoint has to return the same answer: the poller replaces the number
     * in place, and re-deriving the bands in JavaScript is how the colour ends
     * up disagreeing with the score it sits behind.
     */
    public static function scoreBadgeClass(int $score): string
    {
        return match (true) {
            $score >= 75 => 'bg-emerald-100 text-emerald-700',
            $score < 40 => 'bg-red-100 text-red-700',
            default => 'bg-amber-100 text-amber-700',
        };
    }

    /**
     * Tailwind text colour for a STRONG BUY / BUY / NEUTRAL / AVOID verdict.
     */
    public static function verdictTextClass(string $verdict): string
    {
        return match ($verdict) {
            'STRONG BUY' => 'text-emerald-600',
            'BUY' => 'text-primary',
            'NEUTRAL' => 'text-amber-600',
            default => 'text-red-600',
        };
    }

    /**
     * Tailwind classes for the AKUM / DIST money-flow badge.
     *
     * Grey for null, which is not a third state of the flow but the absence of
     * one: VWAP has not been collected, so neither accumulation nor
     * distribution can be claimed.
     */
    public static function flowBadgeClass(?string $flow): string
    {
        return match ($flow) {
            'AKUM' => 'bg-emerald-50 text-emerald-600',
            'DIST' => 'bg-red-50 text-red-600',
            default => 'bg-slate-100 text-slate-400',
        };
    }

    /**
     * Tailwind text colour for a day's percentage change.
     *
     * Grey when the change is unknown, distinct from the grey of an unchanged
     * price: a missing previous close must not be drawn as "flat".
     */
    public static function changeTextClass(?float $changePct): string
    {
        return match (true) {
            $changePct === null => 'text-slate-400',
            $changePct > 0 => 'text-emerald-600',
            $changePct < 0 => 'text-red-600',
            default => 'text-slate-500',
        };
    }

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
