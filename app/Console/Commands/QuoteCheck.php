<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use Illuminate\Console\Command;

/**
 * What a single emiten's daily change is built from.
 *
 * When a stock shows the wrong colour there are only a few possible causes —
 * the stored close is stale, the baseline belongs to the wrong session, or
 * neither figure exists — and none of them are visible from the page. This
 * prints the stored row, the last completed sessions from price history, and
 * the live scan side by side so the disagreement is obvious.
 */
class QuoteCheck extends Command
{
    protected $signature = 'idx:quote-check {ticker : e.g. BBCA} {--live : Also run a TradingView scan and compare}';

    protected $description = 'Show what an emiten\'s daily change is calculated from, to diagnose a wrong colour';

    public function __construct(private readonly MarketDataService $marketData)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ticker = StockRef::normalizeTicker($this->argument('ticker'));
        $price = StockPrice::query()->find($ticker);

        if ($price === null) {
            $this->components->error("No stock_prices row for {$ticker}.");

            return self::FAILURE;
        }

        $this->components->info("Stored row for {$ticker}");
        $this->line(sprintf('  close_price   %s', number_format((float) $price->close_price, 2)));
        $this->line(sprintf('  prev_close    %s', number_format((float) $price->prev_close, 2)));
        $this->line(sprintf('  open_price    %s', number_format((float) $price->open_price, 2)));
        $this->line(sprintf('  updated_at    %s', $price->updated_at?->format('D d M Y H:i') ?? '(never)'));

        $change = $price->dailyChange();
        $pct = $price->dailyChangePct();

        $this->newLine();
        $this->components->info('What the app shows');

        if ($change === null) {
            $this->components->warn('  No baseline at all — rendered grey with no percentage.');
        } else {
            $this->line(sprintf(
                '  %s%s (%s%%)  -> %s',
                $change > 0 ? '+' : '',
                number_format($change, 2),
                round($pct, 2),
                $change > 0 ? 'GREEN' : ($change < 0 ? 'RED' : 'grey (flat)'),
            ));
            $this->line(sprintf(
                '  measured against %s',
                (float) $price->prev_close > 0 ? 'prev_close' : 'open_price (no prev_close stored)',
            ));
        }

        // The independent record of what each session actually closed at.
        $history = StockPriceHistory::query()
            ->where('ticker', $ticker)
            ->orderByDesc('date')
            ->limit(4)
            ->get();

        $this->newLine();
        $this->components->info('Last sessions in price history');

        if ($history->isEmpty()) {
            $this->line('  <fg=gray>(none — run idx:backfill-price-history)</>');
        } else {
            foreach ($history as $bar) {
                $this->line(sprintf('  %s  %s', $bar->date->format('D d M Y'), number_format((float) $bar->close, 2)));
            }

            // This is the check that matters: the baseline should equal the
            // close of the session before the one close_price belongs to.
            $lastCompleted = (float) $history->first()->close;

            if (abs((float) $price->prev_close - $lastCompleted) > 0.01) {
                $this->newLine();
                $this->components->warn(sprintf(
                    'prev_close (%s) does not match the latest completed session (%s).',
                    number_format((float) $price->prev_close, 2),
                    number_format($lastCompleted, 2),
                ));
                $this->line('  <fg=gray>If close_price is today and this is not yesterday, the change spans more than one day.</>');
            }
        }

        if ($this->option('live')) {
            $this->newLine();
            $this->components->info('Live TradingView scan');

            $row = collect($this->marketData->realtimeScan())->firstWhere('ticker', $ticker);

            if ($row === null) {
                $this->components->warn('  Not in the scan (or the scan failed — check the market_data log).');
            } else {
                $this->line(sprintf('  close          %s', number_format($row['close'], 2)));
                $this->line(sprintf(
                    '  previous_close %s',
                    $row['prev_close'] === null ? '(absent — the baseline cannot advance with the price)' : number_format($row['prev_close'], 2),
                ));

                if ($row['prev_close'] !== null) {
                    $liveChange = $row['close'] - $row['prev_close'];
                    $this->line(sprintf(
                        '  would show     %s%s (%s%%)',
                        $liveChange > 0 ? '+' : '',
                        number_format($liveChange, 2),
                        round($liveChange / $row['prev_close'] * 100, 2),
                    ));
                }
            }
        }

        return self::SUCCESS;
    }
}
