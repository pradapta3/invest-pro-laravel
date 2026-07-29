<?php

namespace App\ValueObjects;

use Illuminate\Support\Collection;

/**
 * A strategy run independently across several sequential, non-overlapping
 * sub-periods (e.g. one per calendar year), plus the combined result
 * across the whole range. The point isn't the aggregate number — a
 * strategy can look great in aggregate purely because one lucky period
 * carried it. It's whether performance holds up period over period
 * (isConsistent()) rather than only in hindsight over the full stretch.
 */
final readonly class WalkForwardReport
{
    /**
     * @param  Collection<int, BacktestResult>  $periods
     */
    public function __construct(
        public string $strategy,
        public Collection $periods,
        public BacktestResult $aggregate,
    ) {
    }

    /**
     * True if a majority of sub-periods were individually profitable —
     * a crude but honest robustness signal: a strategy that's only
     * profitable in aggregate because of one strong period is not
     * "consistent", even if its combined numbers look good.
     */
    public function isConsistent(): bool
    {
        if ($this->periods->isEmpty()) {
            return false;
        }

        $profitablePeriods = $this->periods->filter(fn (BacktestResult $p) => $p->totalReturnPct() > 0)->count();

        return $profitablePeriods >= (int) ceil($this->periods->count() / 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'strategy' => $this->strategy,
            'consistent' => $this->isConsistent(),
            'periods' => $this->periods->map(fn (BacktestResult $p) => $p->toArray())->values()->all(),
            'aggregate' => $this->aggregate->toArray(),
        ];
    }
}
