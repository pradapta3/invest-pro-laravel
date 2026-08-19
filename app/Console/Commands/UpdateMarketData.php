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
    protected $signature = 'idx:update-market-data';

    protected $description = 'Refresh OHLCV and technical indicators for every tracked ticker from Yahoo Finance (EOD, 6mo history)';

    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly TechnicalAnalysisService $ta,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tickers = StockRef::query()->orderBy('ticker')->pluck('ticker');
        $bar = $this->output->createProgressBar($tickers->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;

        foreach ($tickers as $ticker) {
            $chart = $this->marketData->dailyChart($ticker, '6mo', '1d');
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
