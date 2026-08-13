<?php

namespace App\Console\Commands;

use App\Services\MarketData\MarketDataService;
use App\Services\MarketData\YahooFinanceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * What Yahoo actually says about the index, next to what the header shows.
 *
 * The header's IHSG figure comes from one upstream call, and when it looks
 * wrong there is no way to tell from the page whether the level is stale, the
 * baseline is the wrong session, or the market is simply shut. This prints the
 * raw session closes alongside the derived numbers so the answer is visible
 * rather than inferred.
 */
class IndexQuoteCheck extends Command
{
    protected $signature = 'idx:index-quote {--symbol=^JKSE} {--fresh : Ignore the cache}';

    protected $description = 'Show the raw and derived IHSG quote, to diagnose a wrong header figure';

    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly YahooFinanceClient $yahoo,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $symbol = (string) $this->option('symbol');
        $tz = config('app.timezone');

        if ($this->option('fresh')) {
            Cache::forget("index-quote:{$symbol}");
        }

        $this->components->info("Raw daily bars for {$symbol}");

        $raw = $this->yahoo->chart($symbol, '5d', '1d');

        if (empty($raw)) {
            $this->components->error('Yahoo returned nothing. The server cannot reach it, or the symbol is wrong.');
            $this->line('  <fg=gray>Check the market_data log; the client stops asking after 3 consecutive failures.</>');

            return self::FAILURE;
        }

        $meta = $raw['meta'] ?? [];
        $timestamps = $raw['timestamp'] ?? [];
        $closes = $raw['indicators']['quote'][0]['close'] ?? [];

        foreach ($timestamps as $i => $ts) {
            $close = $closes[$i] ?? null;

            $this->line(sprintf(
                '  %s  %s',
                Carbon::createFromTimestamp((int) $ts, $tz)->format('D d M Y H:i'),
                $close === null ? '(no data)' : number_format((float) $close, 2),
            ));
        }

        $this->newLine();
        $this->components->info('Meta');
        foreach (['regularMarketPrice', 'previousClose', 'chartPreviousClose', 'regularMarketTime'] as $key) {
            $value = $meta[$key] ?? null;

            if ($key === 'regularMarketTime' && $value !== null) {
                $value = Carbon::createFromTimestamp((int) $value, $tz)->format('D d M Y H:i');
            }

            $this->line(sprintf('  %-20s %s', $key, $value === null ? '(absent)' : $value));
        }

        // chartPreviousClose is what this used to compare against, and the whole
        // point of the change: when it disagrees with the previous session's
        // actual close, the header's percentage was measured from the wrong day.
        $realPrev = null;
        for ($i = count($closes) - 2; $i >= 0; $i--) {
            if (($closes[$i] ?? null) !== null) {
                $realPrev = (float) $closes[$i];
                break;
            }
        }

        if ($realPrev !== null && isset($meta['chartPreviousClose'])) {
            $chartPrev = (float) $meta['chartPreviousClose'];

            $this->newLine();
            if (abs($chartPrev - $realPrev) > 0.01) {
                $this->components->warn(sprintf(
                    'chartPreviousClose (%s) is not the previous session close (%s) — that gap was the wrong percentage.',
                    number_format($chartPrev, 2),
                    number_format($realPrev, 2),
                ));
            } else {
                $this->line('  <fg=gray>chartPreviousClose agrees with the previous session close.</>');
            }
        }

        $this->newLine();
        $this->components->info('What the header will show');

        $quote = $this->marketData->indexQuote($symbol);

        if ($quote === null) {
            $this->components->warn('  indexQuote() returned null — the badge is hidden.');

            return self::SUCCESS;
        }

        $this->line(sprintf('  level      %s', number_format($quote['price'], 2)));
        $this->line(sprintf(
            '  change     %s%s (%s%%)  -> %s',
            $quote['change'] > 0 ? '+' : '',
            number_format($quote['change'], 2),
            round($quote['pct'], 2),
            $quote['stale'] ? 'grey (stale)' : ($quote['change'] > 0 ? 'green' : ($quote['change'] < 0 ? 'red' : 'grey (flat)')),
        ));
        $this->line(sprintf('  as of      %s', $quote['as_of']?->format('D d M Y H:i') ?? '(unknown)'));

        if ($quote['stale']) {
            $this->newLine();
            $this->components->warn('This is the last completed session, not today. The header greys it and shows the date.');
        }

        return self::SUCCESS;
    }
}
