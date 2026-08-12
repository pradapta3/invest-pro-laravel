<?php

namespace App\Console\Commands;

use App\Models\StockFinancial;
use App\Models\StockRef;
use App\Services\MarketData\FinancialStatementParser;
use App\Services\MarketData\YahooFinanceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Annual income statement, balance sheet and cash flow for the last few
 * fiscal years, feeding the statements table on the stock detail page.
 *
 * Separate from idx:update-fundamentals, which fetches the single current
 * snapshot (ROE, PER, DER) the screeners rank on. This one is history, it is
 * one request per emiten, and the figures only change a few times a year, so
 * it belongs on a slow schedule rather than the daily run.
 */
class UpdateFinancials extends Command
{
    protected $signature = 'idx:update-financials
                            {--ticker= : Only this emiten, e.g. BBCA}
                            {--years=5 : How many fiscal years back}
                            {--fresh : Re-fetch emiten that already have rows}';

    protected $description = 'Fetch annual financial statements (5 years) for the stock detail page';

    public function __construct(
        private readonly YahooFinanceClient $yahoo,
        private readonly FinancialStatementParser $parser,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $years = max(1, (int) $this->option('years'));
        $tickers = $this->targets();

        if ($tickers->isEmpty()) {
            $this->components->info('Nothing to fetch.');

            return self::SUCCESS;
        }

        $this->components->info("Fetching {$years} years for {$tickers->count()} emiten");

        $bar = $this->output->createProgressBar($tickers->count());
        $bar->start();

        $updated = 0;
        $empty = 0;
        $failed = 0;

        foreach ($tickers as $ticker) {
            try {
                $rows = $this->parser->parse(
                    $this->yahoo->fundamentalsTimeseries($ticker, FinancialStatementParser::types(), $years)
                );
            } catch (Throwable $e) {
                // One emiten's failure must not abandon the rest of the run;
                // the client's own circuit breaker stops things if upstream is
                // down for everyone.
                $failed++;
                Log::channel('market_data')->warning('Financials fetch failed', [
                    'ticker' => $ticker,
                    'error' => $e->getMessage(),
                ]);
                $bar->advance();

                continue;
            }

            if (empty($rows)) {
                $empty++;
                $bar->advance();

                continue;
            }

            foreach (array_slice($rows, 0, $years) as $row) {
                StockFinancial::query()->updateOrCreate(
                    ['ticker' => $ticker, 'fiscal_year' => $row['fiscal_year']],
                    $row,
                );
            }

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->components->info("Updated {$updated}, no data {$empty}, failed {$failed}.");

        if ($empty > 0) {
            $this->line('  <fg=gray>"No data" is normal for small caps and recent listings — Yahoo has no statements for them.</>');
        }

        if ($failed > 0 && $updated === 0) {
            $this->components->error('Everything failed. Check the market_data log; the client stops after 3 consecutive upstream failures.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function targets()
    {
        if ($one = $this->option('ticker')) {
            return collect([$this->yahoo->normalizeTicker($one)]);
        }

        $query = StockRef::query()->orderBy('ticker');

        if (! $this->option('fresh')) {
            // Default to filling gaps, so an interrupted run resumes cheaply
            // rather than re-fetching the whole exchange.
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('stock_financials')
                    ->whereColumn('stock_financials.ticker', 'stock_refs.ticker');
            });
        }

        return $query->pluck('ticker');
    }
}
