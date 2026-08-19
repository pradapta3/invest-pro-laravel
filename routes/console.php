<?php

use Cron\CronExpression;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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
// Validated rather than passed straight through: an invalid expression throws
// the first time schedule:work evaluates due events, the process exits, Docker's
// restart policy brings it back, and it dies again — a crash loop that stops
// every *other* scheduled command too. A typo in one env var should not cost
// the market-data refresh, the backfill and the alerts.
$realtimeCron = trim((string) config('screener.realtime_cron'));

if (! CronExpression::isValidExpression($realtimeCron)) {
    Log::warning('Invalid IDX_REALTIME_CRON, falling back to every 5 minutes.', [
        'value' => $realtimeCron,
    ]);

    $realtimeCron = '*/5 * * * *';
}

Schedule::command('idx:update-realtime-quotes')
    ->cron($realtimeCron)
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

// Annual statements for the stock detail page. Separate from the snapshot
// above — different endpoint, different shape, and figures that move a few
// times a year rather than weekly.
//
// A slice a day rather than the whole exchange at once. One request per emiten
// means a full sweep is ~900 calls; spread over the refresh interval that is a
// few dozen a night, each run finishes in under a minute, and a night that
// fails costs one slice instead of the lot. The command orders by least
// recently fetched, so consecutive runs rotate through rather than retrying
// the same head of the list.
//
// 40 x 30 days covers roughly 1,200 emiten, comfortably more than IDX lists,
// so every emiten is re-asked within the refresh interval and a newly filed
// annual report shows up within a month of publication without anyone running
// anything by hand.
Schedule::command('idx:update-financials --limit=40')
    ->dailyAt('03:00')
    ->withoutOverlapping(60);
