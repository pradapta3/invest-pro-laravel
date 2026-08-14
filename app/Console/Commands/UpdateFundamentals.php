<?php

namespace App\Console\Commands;

use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use Illuminate\Console\Command;

/**
 * Refreshes ROE/PER/PBV/DER/market cap via Yahoo's crumb-authenticated
 * quoteSummary endpoint, replacing update_fundamentals.php.
 */
class UpdateFundamentals extends Command
{
    protected $signature = 'idx:update-fundamentals';

    protected $description = 'Refresh ROE/PER/PBV/DER/market cap for every tracked ticker via Yahoo Finance';

    public function __construct(private readonly MarketDataService $marketData)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tickers = StockRef::query()->orderBy('ticker')->pluck('ticker');
        $bar = $this->output->createProgressBar($tickers->count());
        $bar->start();

        $updated = 0;

        foreach ($tickers as $ticker) {
            $fundamentals = $this->marketData->fundamentals($ticker);
            $hasData = $fundamentals['roe'] != 0 || $fundamentals['per'] != 0 || $fundamentals['market_cap'] != 0;

            if ($hasData) {
                StockRef::query()->where('ticker', $ticker)->update([
                    'roe' => $fundamentals['roe'],
                    'pe_ratio' => $fundamentals['per'],
                    'pb_ratio' => $fundamentals['pbv'],
                    // Stored rather than derived at render time, so a company
                    // between annual filings still has one.
                    'eps' => $fundamentals['eps'] > 0 ? $fundamentals['eps'] : null,
                    'der' => $fundamentals['der'],
                    'market_cap' => $fundamentals['market_cap'],
                ]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated fundamentals for {$updated} of {$tickers->count()} tickers.");

        return self::SUCCESS;
    }
}
