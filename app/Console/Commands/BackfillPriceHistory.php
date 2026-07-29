<?php

namespace App\Console\Commands;

use App\Models\StockPriceHistory;
use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Backfills multi-year daily OHLCV into stock_price_histories — the raw
 * material BacktestEngine needs to reconstruct indicator values (MA20,
 * RSI, volume averages, ...) at every historical date, not just today's
 * snapshot. Run this once before backtesting, and periodically afterward
 * (it upserts, so re-running is safe and just fills in new days).
 */
class BackfillPriceHistory extends Command
{
    protected $signature = 'idx:backfill-price-history
        {--years=2 : How many years of history to fetch (1, 2, 5, or 10)}
        {--tickers=* : Specific tickers to backfill instead of the whole universe, e.g. --tickers=BBCA --tickers=BBRI}';

    protected $description = 'Backfill multi-year daily OHLCV history for backtesting';

    private const YEAR_TO_RANGE = [1 => '1y', 2 => '2y', 5 => '5y', 10 => '10y'];

    public function __construct(private readonly MarketDataService $marketData)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $range = self::YEAR_TO_RANGE[$years] ?? '2y';

        $tickerOption = $this->option('tickers');
        $tickers = ! empty($tickerOption)
            ? collect($tickerOption)->map(fn (string $t) => StockRef::normalizeTicker($t))
            : StockRef::query()->orderBy('ticker')->pluck('ticker');

        $bar = $this->output->createProgressBar($tickers->count());
        $bar->start();

        $totalRows = 0;
        $failed = 0;

        foreach ($tickers as $ticker) {
            $chart = $this->marketData->dailyChart($ticker, $range, '1d');
            $rows = $this->buildRows($ticker, $chart);

            if (empty($rows)) {
                $failed++;
                $bar->advance();

                continue;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                StockPriceHistory::query()->upsert(
                    $chunk,
                    ['ticker', 'date'],
                    ['open', 'high', 'low', 'close', 'volume', 'updated_at']
                );
            }

            $totalRows += count($rows);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Backfilled {$totalRows} daily bars across ".($tickers->count() - $failed)." tickers ({$failed} had no data).");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(string $ticker, array $chart): array
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($chart['timestamps'] ?? [] as $i => $timestamp) {
            $close = $chart['close'][$i] ?? null;
            if ($close === null || $close <= 0) {
                continue; // Yahoo returns null bars for market holidays inside the range.
            }

            $rows[] = [
                'ticker' => $ticker,
                'date' => Carbon::createFromTimestamp($timestamp)->toDateString(),
                'open' => $chart['open'][$i] ?? 0,
                'high' => $chart['high'][$i] ?? 0,
                'low' => $chart['low'][$i] ?? 0,
                'close' => $close,
                'volume' => $chart['volume'][$i] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
