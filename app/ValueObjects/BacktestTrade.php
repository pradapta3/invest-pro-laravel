<?php

namespace App\ValueObjects;

use Carbon\Carbon;

/**
 * One simulated round-trip trade produced by BacktestEngine.
 */
final readonly class BacktestTrade
{
    public function __construct(
        public string $ticker,
        public Carbon $entryDate,
        public float $entryPrice,
        public Carbon $exitDate,
        public float $exitPrice,
        public string $exitReason,
        public int $holdingDays,
        public float $buyFeeRate = 0.0,
        public float $sellFeeRate = 0.0,
    ) {
    }

    /**
     * Net of buy/sell fees (see config/trading.php) — same convention as
     * PortfolioService and TechnicalAnalysisService::backtestMa20Strategy():
     * cost to enter is entryPrice * (1 + buyFeeRate), proceeds on exit are
     * exitPrice * (1 - sellFeeRate). Without this, a backtest could report
     * a "winning" trade that would have been a net loss after real fees.
     */
    public function returnPct(): float
    {
        $netEntryCost = $this->entryPrice * (1 + $this->buyFeeRate);

        if ($netEntryCost <= 0) {
            return 0.0;
        }

        $netExitProceeds = $this->exitPrice * (1 - $this->sellFeeRate);

        return (($netExitProceeds - $netEntryCost) / $netEntryCost) * 100;
    }

    public function isWin(): bool
    {
        return $this->returnPct() > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ticker' => str_replace('.JK', '', $this->ticker),
            'entry_date' => $this->entryDate->toDateString(),
            'entry_price' => $this->entryPrice,
            'exit_date' => $this->exitDate->toDateString(),
            'exit_price' => $this->exitPrice,
            'exit_reason' => $this->exitReason,
            'holding_days' => $this->holdingDays,
            'return_pct' => round($this->returnPct(), 2),
        ];
    }
}
