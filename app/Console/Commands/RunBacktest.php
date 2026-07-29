<?php

namespace App\Console\Commands;

use App\Models\StockRef;
use App\Services\Backtesting\BacktestEngine;
use App\ValueObjects\BacktestResult;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * CLI front-end for BacktestEngine — quick way to sanity-check a strategy
 * without opening the /backtest web page.
 */
class RunBacktest extends Command
{
    protected $signature = 'idx:backtest
        {strategy : bsjp, titan, trend_runner, pullback_sniper, or bandar_radar}
        {--from= : Start date (Y-m-d), defaults to 2 years ago}
        {--to= : End date (Y-m-d), defaults to today}
        {--tickers=* : Restrict to specific tickers, e.g. --tickers=BBCA --tickers=BBRI}
        {--walk-forward : Run yearly sub-periods separately instead of one combined range}';

    protected $description = 'Backtest a StockScreenerService strategy against stored historical price data';

    public function __construct(private readonly BacktestEngine $engine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $strategy = $this->argument('strategy');

        if (! in_array($strategy, BacktestEngine::SUPPORTED_STRATEGIES, true)) {
            $this->error("'{$strategy}' can't be backtested — ".BacktestEngine::UNSUPPORTED_STRATEGY_REASON);
            $this->line('Supported: '.implode(', ', BacktestEngine::SUPPORTED_STRATEGIES));

            return self::FAILURE;
        }

        $from = Carbon::parse($this->option('from') ?? now()->subYears(2)->toDateString());
        $to = Carbon::parse($this->option('to') ?? now()->toDateString());
        $tickers = ! empty($this->option('tickers'))
            ? collect($this->option('tickers'))->map(fn (string $t) => \App\Models\StockRef::normalizeTicker($t))
            : null;

        try {
            if ($this->option('walk-forward')) {
                $report = $this->engine->runWalkForward($strategy, $from, $to, $tickers);

                foreach ($report->periods as $period) {
                    $this->printResult($period);
                }

                $this->line('');
                $this->info('=== Aggregate ===');
                $this->printResult($report->aggregate);
                $this->line('');
                $this->line('Consistent across periods: '.($report->isConsistent() ? 'YES' : 'NO — treat the aggregate with suspicion'));
            } else {
                $this->printResult($this->engine->run($strategy, $from, $to, $tickers));
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function printResult(BacktestResult $result): void
    {
        $this->line("<fg=cyan>{$result->strategy}</> {$result->periodStart->toDateString()} \u{2192} {$result->periodEnd->toDateString()}");

        if ($result->tradeCount() === 0) {
            $this->line('  No trades triggered in this period.');

            return;
        }

        $this->table(
            ['Trades', 'Win rate', 'Avg return', 'Total return', 'Max drawdown', 'Profit factor', 'Sharpe', 'Avg hold (days)'],
            [[
                $result->tradeCount(),
                round($result->winRate(), 1).'%',
                round($result->avgReturnPct(), 2).'%',
                round($result->totalReturnPct(), 2).'%',
                round($result->maxDrawdownPct(), 2).'%',
                $result->profitFactor() !== null ? round($result->profitFactor(), 2) : '∞ (no losses)',
                $result->sharpeRatio() !== null ? round($result->sharpeRatio(), 2) : 'n/a',
                round($result->avgHoldingDays(), 1),
            ]]
        );
    }
}
