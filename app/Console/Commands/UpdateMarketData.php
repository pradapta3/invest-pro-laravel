<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use App\Services\TechnicalAnalysisService;
use Illuminate\Console\Command;

/**
 * End-of-day OHLCV + technical indicator refresh, replacing
 * update_market.php. Unlike the legacy script, stoch_k and macd_hist are
 * computed for real via TechnicalAnalysisService — update_market.php
 * hardcoded `$stoch = 50;` and `$macd = ($last_c > $ma20) ? 0.5 : -0.5;`
 * as placeholders that were never replaced with actual math. Also does
 * not perform ad-hoc `ALTER TABLE` calls on every run the way the legacy
 * script did; the schema is fixed by migrations up front.
 */
class UpdateMarketData extends Command
{
    protected $signature = 'idx:update-market-data
        {--tickers=* : Refresh only these tickers, e.g. --tickers=BYAN --tickers=BBCA}';

    protected $description = 'Refresh OHLCV and technical indicators for every tracked ticker from Yahoo Finance (EOD, 6mo history)';

    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly TechnicalAnalysisService $ta,
    ) {
        parent::__construct();
    }

    /**
     * Drop the bars where nothing traded, keeping every series aligned.
     *
     * Yahoo returns null for a day an emiten did not trade — a suspension, or
     * an exchange holiday inside the requested range — and MarketDataService
     * coerces those nulls to 0.0. Fed to the indicators they are read as a
     * session that closed at zero: three suspended days in a 40-day series
     * move MA20 from 1,647 to 1,399 and RSI from 100 to 52, which is enough
     * to flip the trend component of the score and the entry side of the
     * trading plan. A halt on the most recent bar writes close_price = 0.
     *
     * idx:backfill-price-history has always skipped them, one command over.
     * The rule belongs to both.
     *
     * @param  array{timestamps: int[], open: float[], high: float[], low: float[], close: float[], volume: int[], meta: array}  $chart
     * @return array{timestamps: int[], open: float[], high: float[], low: float[], close: float[], volume: int[], meta: array}
     */
    private function tradedBarsOnly(array $chart): array
    {
        // The close decides, and every other series follows its indices — a
        // bar filtered out of the closes but left in the highs would silently
        // pair each day's close with the next day's high.
        $keep = array_keys(array_filter($chart['close'] ?? [], fn (float $c) => $c > 0));

        foreach (['timestamps', 'open', 'high', 'low', 'close', 'volume'] as $series) {
            $chart[$series] = array_values(array_intersect_key($chart[$series] ?? [], array_flip($keep)));
        }

        return $chart;
    }

    public function handle(): int
    {
        // Scoping to a few tickers, as idx:backfill-price-history already
        // allows: after fixing one emiten's data there is no reason to spend
        // ~900 Yahoo requests re-fetching the rest of the exchange.
        $only = $this->option('tickers');
        $tickers = ! empty($only)
            ? collect($only)->map(fn (string $t) => StockRef::normalizeTicker($t))
            : StockRef::query()->orderBy('ticker')->pluck('ticker');

        $bar = $this->output->createProgressBar($tickers->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;

        foreach ($tickers as $ticker) {
            $chart = $this->tradedBarsOnly($this->marketData->dailyChart($ticker, '6mo', '1d'));
            $closes = $chart['close'] ?? [];

            if (count($closes) <= 30) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $highs = $chart['high'];
            $lows = $chart['low'];
            $opens = $chart['open'];
            $volumes = $chart['volume'];

            $lastClose = end($closes);
            $prevClose = $closes[count($closes) - 2];
            // The session that baseline closed, carried through so the daily
            // change can be checked rather than trusted. Without it a baseline
            // that stops advancing is indistinguishable from a correct one,
            // and the change silently becomes a multi-day move.
            $prevCloseDate = isset($chart['timestamps'][count($closes) - 2])
                ? date('Y-m-d', $chart['timestamps'][count($closes) - 2])
                : null;
            $ma20 = $this->ta->sma($closes, 20);
            $macd = $this->ta->macd($closes);
            $stoch = $this->ta->stochastic($closes, $highs, $lows);

            $prevWindowMax = max(array_slice($closes, -21, 20));
            $isBreakout = $lastClose > $prevWindowMax && $prevClose <= $prevWindowMax;

            StockPrice::query()->updateOrCreate(
                ['ticker' => $ticker],
                [
                    'open_price' => end($opens),
                    'high_price' => max(array_slice($highs, -1)) ?: end($highs),
                    'low_price' => end($lows),
                    'close_price' => $lastClose,
                    'prev_close' => $prevClose,
                    'prev_close_date' => $prevCloseDate,
                    'volume' => end($volumes),
                    'ma20' => $ma20,
                    'rsi_14' => $this->ta->rsi($closes),
                    'stoch_k' => $stoch['k'],
                    'macd_hist' => $macd['hist'],
                    'vol_avg_20' => $this->ta->sma($volumes, 20),
                    'is_breakout' => $isBreakout,
                    'history_json' => array_slice($closes, -60),
                ],
            );

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} tickers, skipped {$skipped} (insufficient history).");

        return self::SUCCESS;
    }
}
