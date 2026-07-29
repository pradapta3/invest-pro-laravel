<?php

namespace App\ValueObjects;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregate performance metrics for one strategy over one period, produced
 * by BacktestEngine::run().
 *
 * All metrics here treat every trade as an *independent, equally-sized*
 * position (e.g. "if you'd taken every signal with the same fixed stake")
 * — this is a signal-quality backtest, not a capital-constrained portfolio
 * simulation. It does not model running out of cash, position limits, or
 * multiple simultaneous trades competing for the same capital. Real
 * concurrent-position sizing is a materially bigger project (see
 * PortfolioService for the actual capital-tracked simulator); treat these
 * numbers as "was this rule statistically worth following", not "this is
 * exactly how much money you'd have made".
 */
final readonly class BacktestResult
{
    /**
     * @param  Collection<int, BacktestTrade>  $trades
     */
    public function __construct(
        public string $strategy,
        public Carbon $periodStart,
        public Carbon $periodEnd,
        public Collection $trades,
    ) {
    }

    public function tradeCount(): int
    {
        return $this->trades->count();
    }

    public function winRate(): float
    {
        if ($this->trades->isEmpty()) {
            return 0.0;
        }

        return ($this->trades->filter(fn (BacktestTrade $t) => $t->isWin())->count() / $this->trades->count()) * 100;
    }

    public function avgReturnPct(): float
    {
        return $this->trades->isEmpty() ? 0.0 : (float) $this->trades->avg(fn (BacktestTrade $t) => $t->returnPct());
    }

    public function avgHoldingDays(): float
    {
        return $this->trades->isEmpty() ? 0.0 : (float) $this->trades->avg(fn (BacktestTrade $t) => $t->holdingDays);
    }

    /**
     * Gross profit / gross loss. Null means there were no losing trades
     * (an undefined/"infinite" ratio) rather than reporting a misleading 0.
     */
    public function profitFactor(): ?float
    {
        $grossWin = (float) $this->trades->filter(fn (BacktestTrade $t) => $t->returnPct() > 0)->sum(fn (BacktestTrade $t) => $t->returnPct());
        $grossLoss = abs((float) $this->trades->filter(fn (BacktestTrade $t) => $t->returnPct() < 0)->sum(fn (BacktestTrade $t) => $t->returnPct()));

        if ($grossLoss == 0.0) {
            return null;
        }

        return $grossWin / $grossLoss;
    }

    /**
     * Sum of every trade's % return, in exit-date order — the "equal-
     * stake" cumulative P&L curve described in the class docblock.
     */
    public function totalReturnPct(): float
    {
        return (float) $this->trades->sum(fn (BacktestTrade $t) => $t->returnPct());
    }

    /**
     * Largest peak-to-trough drop in the cumulative P&L curve above.
     */
    public function maxDrawdownPct(): float
    {
        $cumulative = 0.0;
        $peak = 0.0;
        $maxDrawdown = 0.0;

        foreach ($this->sortedTrades() as $trade) {
            $cumulative += $trade->returnPct();
            $peak = max($peak, $cumulative);
            $maxDrawdown = max($maxDrawdown, $peak - $cumulative);
        }

        return $maxDrawdown;
    }

    /**
     * Mean/stdev of per-trade returns, scaled by sqrt(trades per year) as
     * a rough annualization. This is a per-signal Sharpe, not a
     * timeseries-of-daily-returns Sharpe — treat it as directional
     * (higher is better, and compare strategies on equal footing), not as
     * a literal "expected annualized risk-adjusted return" figure.
     */
    public function sharpeRatio(): ?float
    {
        $returns = $this->trades->map(fn (BacktestTrade $t) => $t->returnPct())->values()->all();
        $n = count($returns);

        if ($n < 2) {
            return null;
        }

        $mean = array_sum($returns) / $n;
        $variance = array_sum(array_map(fn ($r) => ($r - $mean) ** 2, $returns)) / ($n - 1);
        $stdDev = sqrt($variance);

        if ($stdDev == 0.0) {
            return null;
        }

        $years = max(0.01, $this->periodStart->diffInDays($this->periodEnd) / 365);
        $tradesPerYear = $n / $years;

        return ($mean / $stdDev) * sqrt($tradesPerYear);
    }

    /**
     * @return array<int, array{date: string, cumulative_return_pct: float}>
     */
    public function equityCurve(): array
    {
        $cumulative = 0.0;
        $points = [];

        foreach ($this->sortedTrades() as $trade) {
            $cumulative += $trade->returnPct();
            $points[] = ['date' => $trade->exitDate->toDateString(), 'cumulative_return_pct' => round($cumulative, 2)];
        }

        return $points;
    }

    /**
     * @return Collection<int, BacktestTrade>
     */
    private function sortedTrades(): Collection
    {
        return $this->trades->sortBy(fn (BacktestTrade $t) => $t->exitDate->timestamp)->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'strategy' => $this->strategy,
            'period_start' => $this->periodStart->toDateString(),
            'period_end' => $this->periodEnd->toDateString(),
            'trade_count' => $this->tradeCount(),
            'win_rate_pct' => round($this->winRate(), 1),
            'avg_return_pct' => round($this->avgReturnPct(), 2),
            'total_return_pct' => round($this->totalReturnPct(), 2),
            'max_drawdown_pct' => round($this->maxDrawdownPct(), 2),
            'profit_factor' => $this->profitFactor() !== null ? round($this->profitFactor(), 2) : null,
            'sharpe_ratio' => $this->sharpeRatio() !== null ? round($this->sharpeRatio(), 2) : null,
            'avg_holding_days' => round($this->avgHoldingDays(), 1),
        ];
    }
}
