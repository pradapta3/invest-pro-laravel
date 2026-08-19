<?php

namespace App\Support;

/**
 * IDX price fractions ("fraksi harga").
 *
 * The exchange only accepts orders at multiples of a step that widens with
 * the price, so a level like Rp1,597 or Rp9,312 cannot be entered at all —
 * a Rp1,613 stock moves in Rp5 and a Rp9,750 stock in Rp25. Every level the
 * app puts in front of someone as an entry, target or stop has to be a price
 * they can actually type into a broker.
 */
final class IdxPrice
{
    /**
     * Step size for a given price level. Boundaries are inclusive at the
     * bottom, exclusive at the top, matching the exchange's table.
     *
     * @return int rupiah per step
     */
    public static function tick(float $price): int
    {
        return match (true) {
            $price < 200 => 1,
            $price < 500 => 2,
            $price < 2_000 => 5,
            $price < 5_000 => 10,
            default => 25,
        };
    }

    /**
     * The exchange's auto-rejection band for a price level, in percent.
     *
     * IDX refuses orders more than this far from the previous close, so a
     * single session's move cannot exceed it. That makes it a validity test
     * rather than a trading rule: a computed daily change well beyond this
     * band is not a big day, it is a baseline and a price that are not
     * describing the same instrument — history adjusted for a stock split
     * while the live quote is not, most often.
     *
     * The percentages are the exchange's own and it does revise them (they
     * were asymmetric before 2023), so they are config-overridable. Nothing
     * here depends on them being exactly current: they are used with generous
     * headroom, to catch the impossible rather than the merely unusual.
     */
    public static function autoRejectionPct(float $price): float
    {
        $bands = config('screener.auto_rejection_bands');

        return match (true) {
            $price < 200 => (float) $bands['under_200'],
            $price < 5_000 => (float) $bands['under_5000'],
            default => (float) $bands['above_5000'],
        };
    }

    /**
     * Nearest tradeable price at or below $price.
     *
     * Used for levels where paying less is the safer error: a buy limit, and
     * the low end of an entry band.
     */
    public static function floorToTick(float $price): float
    {
        $tick = self::tick($price);

        return $price <= 0 ? 0.0 : floor($price / $tick) * $tick;
    }

    /**
     * Nearest tradeable price at or above $price.
     */
    public static function ceilToTick(float $price): float
    {
        $tick = self::tick($price);

        return $price <= 0 ? 0.0 : ceil($price / $tick) * $tick;
    }

    /**
     * Nearest tradeable price in either direction.
     *
     * Note the tick is taken from the *rounded* result as well, so a price
     * sitting just under a band boundary does not round up past it onto a
     * step that is no longer valid there — 4,998 rounds to 5,000 on the Rp10
     * step and stays valid, but 199.6 must not become 200 on the Rp1 step
     * and then be judged against Rp2.
     */
    public static function roundToTick(float $price): float
    {
        if ($price <= 0) {
            return 0.0;
        }

        $rounded = round($price / self::tick($price)) * self::tick($price);

        return round($rounded / self::tick($rounded)) * self::tick($rounded);
    }
}
