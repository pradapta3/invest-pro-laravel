<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Whether the exchange is trading, and whether a stored quote is still fresh.
 *
 * Both questions were answered ad hoc before — the scheduler knew the trading
 * window through `->weekdays()->between()`, and nothing else knew it at all.
 * The consequence was a dashboard that presented a Friday-afternoon price on
 * Sunday exactly as it presents a live one, with no way for a reader to tell.
 *
 * Public holidays are not modelled. IDX closes on a dozen or so days a year
 * that no rule derives, and there is no calendar in this app to consult, so
 * the exchange reads as open on those days and the freshness check below
 * catches it instead: nothing has been written for hours, so the figure is
 * marked stale rather than passed off as current.
 */
final class MarketClock
{
    public static function now(): Carbon
    {
        return Carbon::now(config('app.timezone'));
    }

    /**
     * Inside the trading window, on a weekday.
     *
     * The same window the scheduler runs idx:update-realtime-quotes in, read
     * from config so the two cannot drift apart.
     */
    public static function isOpen(?Carbon $at = null): bool
    {
        $at ??= self::now();

        if ($at->isWeekend()) {
            return false;
        }

        $open = $at->copy()->setTimeFromTimeString(config('screener.market_open'));
        $close = $at->copy()->setTimeFromTimeString(config('screener.market_close'));

        return $at->betweenIncluded($open, $close);
    }

    /**
     * How old a quote is allowed to be before it stops counting as current.
     *
     * Only meaningful while the exchange is trading. Outside the session
     * nothing is being written and the last close is the correct thing to
     * show — it is simply not "live", which is what isOpen() is for.
     */
    public static function isStale(?Carbon $updatedAt): bool
    {
        if (! self::isOpen()) {
            return false;
        }

        if ($updatedAt === null) {
            return true;
        }

        return $updatedAt->diffInSeconds(self::now()) > (int) config('screener.quote_stale_after_seconds');
    }
}
