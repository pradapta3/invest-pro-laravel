<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled market-data refresh
|--------------------------------------------------------------------------
|
| The legacy app had no real automation — update_market.php,
| update_realtime.php etc. were only ever run by a human opening the page
| in a browser. Cadences below are a reasonable starting point (IDX
| trading hours are 09:00-15:50 WIB on weekdays); tune to taste and make
| sure `php artisan schedule:work` (or a system cron calling
| `schedule:run` every minute) is actually running in production.
|
*/

// Cadence comes from config so it can be tuned per deployment without editing
// code — set IDX_REALTIME_CRON in .env. Default is every 5 minutes; '* * * * *'
// gives near-live quotes. cron() sets the whole expression, then weekdays()
// rewrites its day-of-week field, so the two compose rather than conflict.
//
// withoutOverlapping takes an explicit 10-minute lock expiry: on the default
// (1440 minutes) a run killed mid-flight — a container restart, say — would
// hold the lock for a day and silently stop every later run. At this cadence
// that would be invisible until someone noticed stale prices.
Schedule::command('idx:update-realtime-quotes')
    ->cron(config('screener.realtime_cron'))
    ->weekdays()
    ->between('09:00', '16:00')
    ->withoutOverlapping(10);

// Runs right after the realtime refresh above (registered later in the
// same schedule:run tick, so it always sees today's just-updated
// close_price) — checks watchlist alerts and portfolio SL/TP, notifies
// over Telegram. See CheckPriceAlerts.
//
// Deliberately on its own 15-minute cadence rather than following the one
// above: this one sends a Telegram message per triggered alert, so running it
// as often as the quote refresh turns a stock hovering at its target into a
// stream of notifications.
Schedule::command('idx:check-price-alerts')
    ->weekdays()
    ->between('09:00', '16:00')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);

Schedule::command('idx:update-market-data')
    ->weekdays()
    ->dailyAt('16:15')
    ->withoutOverlapping();

// Keeps stock_price_histories (the raw material BacktestEngine reads) current.
// --years=1 is enough since this only needs to catch today's new bar — the
// upsert is keyed on (ticker, date), so re-fetching a rolling window daily
// is safe and just fills in whatever's new since yesterday.
Schedule::command('idx:backfill-price-history --years=1')
    ->weekdays()
    ->dailyAt('16:30')
    ->withoutOverlapping();

Schedule::command('idx:update-news-sentiment')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('idx:update-fundamentals')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->withoutOverlapping();
