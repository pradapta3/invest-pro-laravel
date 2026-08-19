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
                            {--limit=0 : Stop after this many emiten (0 = no cap). The schedule uses this to rotate through the exchange a slice at a time.}
                            {--fresh : Ignore how recently an emiten was fetched and refetch everything}';

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
                $series = $this->yahoo->fundamentalsTimeseries($ticker, FinancialStatementParser::types(), $years);

                if ($series === null) {
                    // Could not ask — timeout, reset, or the client's circuit
                    // breaker already open. Not the same as "Yahoo has nothing",
                    // so it is not marked as fetched and the next run retries.
                    $failed++;
                    $bar->advance();

                    continue;
                }

                $rows = $this->parser->parse($series);
            } catch (Throwable $e) {
                // One emiten's failure must not abandon the rest of the run;
                // the client's own circuit breaker stops things if upstream is
                // down for everyone. Deliberately not marked as fetched, so the
                // next run retries it rather than waiting out the full refresh
                // interval on a transient error.
                $failed++;
                Log::channel('market_data')->warning('Financials fetch failed', [
                    'ticker' => $ticker,
                    'error' => $e->getMessage(),
                ]);
                $bar->advance();

                continue;
            }

            if (empty($rows)) {
                // A clean answer of "nothing here". Marked, so this emiten does
                // not reappear at the head of the queue on every single run.
                $this->markFetched($ticker);
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

            $this->markFetched($ticker);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->components->info("Updated {$updated}, no data {$empty}, failed {$failed}.");

        if ($empty > 0) {
            $this->line('  <fg=gray>"No data" is normal for small caps and recent listings — Yahoo has no statements for them.</>');
        }

        if ($failed > 0) {
            $this->line('  <fg=gray>Failures are not marked as fetched, so the next run retries them.</>');
        }

        if ($failed > 0 && $updated === 0) {
            $this->components->error('Everything failed. Check the market_data log; the client stops asking after 3 consecutive upstream failures.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Emiten to fetch, least recently asked about first.
     *
     * "Never fetched" is not the test. This used to select only emiten with no
     * rows at all, which is right for resuming an interrupted first run and
     * useless for everything after it: once every emiten had rows, every later
     * run found nothing to do and a newly published annual report would never
     * arrive. Staleness is the test instead, so the schedule keeps working
     * without anyone remembering to pass --fresh once a year.
     *
     * Oldest first, so a --limit run rotates through the exchange rather than
     * refetching the same head of the list every day.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function targets()
    {
        if ($one = $this->option('ticker')) {
            return collect([$this->yahoo->normalizeTicker($one)]);
        }

        $query = StockRef::query()
            ->orderByRaw('financials_fetched_at IS NULL DESC')
            ->orderBy('financials_fetched_at')
            ->orderBy('ticker');

        if (! $this->option('fresh')) {
            $staleBefore = now()->subDays((int) config('screener.financial_refresh_days'));

            $query->where(function ($q) use ($staleBefore) {
                $q->whereNull('financials_fetched_at')
                    ->orWhere('financials_fetched_at', '<', $staleBefore);
            });
        }

        if (($limit = (int) $this->option('limit')) > 0) {
            $query->limit($limit);
        }

        return $query->pluck('ticker');
    }

    /**
     * Record that we asked, whatever the answer was.
     *
     * Not stock_financials.updated_at: updateOrCreate leaves child rows
     * untouched when nothing changed — which is almost every run, since annual
     * figures move a few times a year — so their timestamps would report an
     * emiten as permanently stale. And an emiten Yahoo has no statements for
     * has no rows to timestamp at all, so without this the small caps would
     * consume the whole rotation budget on every run, forever.
     */
    private function markFetched(string $ticker): void
    {
        StockRef::query()->whereKey($ticker)->update(['financials_fetched_at' => now()]);
    }
}
