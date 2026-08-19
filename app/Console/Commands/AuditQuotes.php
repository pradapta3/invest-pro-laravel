<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use App\Support\IdxPrice;
use App\Support\MarketClock;
use Illuminate\Console\Command;

/**
 * Sweeps every emiten for the defects that make a daily change wrong, and
 * names the ones affected.
 *
 * idx:quote-check answers "why is this one emiten wrong", which is the right
 * question once you already suspect a particular one. It is the wrong question
 * for a board of nine hundred: a stock showing the wrong colour is only
 * noticed if someone happens to know what it did today, and the ones nobody
 * watches stay wrong indefinitely. This asks the question of all of them at
 * once.
 *
 * Everything here reads stored data. It makes no upstream requests, so it is
 * safe to run at any time and says nothing about whether the feeds are
 * reachable — only about what they have left behind.
 */
class AuditQuotes extends Command
{
    protected $signature = 'idx:audit-quotes
        {--limit=40 : How many affected emiten to list per problem}
        {--all : List every affected emiten instead of the first --limit}';

    protected $description = 'Find every emiten whose daily change, price or indicators cannot be trusted';

    public function handle(): int
    {
        $rows = StockPrice::query()->with('stockRef')->get();

        if ($rows->isEmpty()) {
            $this->components->error('No stock_prices rows — run idx:update-market-data first.');

            return self::FAILURE;
        }

        $history = StockPriceHistory::lastCompletedSessions();
        $newestWrite = $rows->max('updated_at');

        $problems = [
            'No previous close stored' => [],
            'Baseline older than the change it describes' => [],
            'Row frozen — not in the scan' => [],
            'Move exceeds the exchange auto-rejection band' => [],
            'Price is zero' => [],
            'Indicators never computed' => [],
            'No price history to repair from' => [],
        ];

        foreach ($rows as $row) {
            $ticker = $row->stockRef?->cleanTicker() ?? str_replace('.JK', '', $row->ticker);
            $close = (float) $row->close_price;
            $base = (float) $row->prev_close;

            if ($close <= 0) {
                $problems['Price is zero'][] = [$ticker, '—', 'close_price is 0; a halted last bar written as a real close'];

                // Everything downstream of the price is meaningless once the
                // price is zero, and reporting a "-100% move, likely a
                // corporate action" alongside it points at the wrong cause.
                continue;
            }

            if (! $row->hasIndicators()) {
                $problems['Indicators never computed'][] = [$ticker, '—', 'ma20 is 0 — score and trading plan are meaningless for this row'];
            }

            if (! isset($history[$row->ticker])) {
                $problems['No price history to repair from'][] = [$ticker, '—', 'idx:backfill-price-history --tickers='.$ticker];
            }

            if ($base <= 0) {
                $problems['No previous close stored'][] = [$ticker, number_format($close), 'shows n/a until a refresh supplies one'];

                continue;
            }

            if ($row->baselineIsStale()) {
                $frozen = $row->updated_at !== null && MarketClock::isStale($row->updated_at);
                $bucket = $frozen ? 'Row frozen — not in the scan' : 'Baseline older than the change it describes';

                $problems[$bucket][] = [
                    $ticker,
                    number_format($close),
                    sprintf(
                        'baseline %s, row written %s',
                        $row->prev_close_date?->format('d M') ?? '(undated)',
                        $row->updated_at?->format('d M H:i') ?? 'never',
                    ),
                ];

                continue;
            }

            if (! $row->dailyChangeIsPlausible()) {
                $movePct = ($close - $base) / $base * 100;

                $problems['Move exceeds the exchange auto-rejection band'][] = [
                    $ticker,
                    number_format($close),
                    sprintf(
                        '%+.1f%% against a baseline of %s (band %s%%) — likely an unadjusted corporate action',
                        $movePct,
                        number_format($base),
                        IdxPrice::autoRejectionPct($base),
                    ),
                ];
            }
        }

        return $this->report($problems, $rows->count(), $newestWrite);
    }

    /**
     * @param  array<string, array<int, array{0: string, 1: string, 2: string}>>  $problems
     */
    private function report(array $problems, int $total, mixed $newestWrite): int
    {
        $limit = $this->option('all') ? PHP_INT_MAX : max(1, (int) $this->option('limit'));

        $this->newLine();
        $this->components->info(sprintf(
            '%d emiten checked. Newest write %s. Market %s.',
            $total,
            $newestWrite ? $newestWrite->format('D d M Y H:i') : 'never',
            MarketClock::isOpen() ? 'open' : 'closed',
        ));

        $affected = 0;

        foreach ($problems as $label => $found) {
            if (empty($found)) {
                continue;
            }

            $affected += count($found);

            $this->newLine();
            $this->components->warn(sprintf('%s — %d emiten', $label, count($found)));

            foreach (array_slice($found, 0, $limit) as [$ticker, $price, $detail]) {
                $this->line(sprintf('  %-8s %-10s <fg=gray>%s</>', $ticker, $price, $detail));
            }

            if (count($found) > $limit) {
                $this->line(sprintf('  <fg=gray>... and %d more (--all to list them)</>', count($found) - $limit));
            }
        }

        $this->newLine();

        if ($affected === 0) {
            $this->components->info('Nothing to report — every emiten can state a daily change.');

            return self::SUCCESS;
        }

        // Counted per problem, so an emiten with two of them appears twice;
        // the number is a workload, not a headcount.
        $this->components->warn("{$affected} finding(s) across {$total} emiten.");
        $this->line('  <fg=gray>idx:quote-check <ticker> explains any one of them in full.</>');

        return self::SUCCESS;
    }
}
