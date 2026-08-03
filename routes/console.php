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

Schedule::command('idx:update-realtime-quotes')
    ->weekdays()
    ->between('09:00', '16:00')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Runs right after the realtime refresh above (registered later in the
// same schedule:run tick, so it always sees today's just-updated
// close_price) — checks watchlist alerts and portfolio SL/TP, notifies
// over Telegram. See CheckPriceAlerts.
Schedule::command('idx:check-price-alerts')
    ->weekdays()
    ->between('09:00', '16:00')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

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
