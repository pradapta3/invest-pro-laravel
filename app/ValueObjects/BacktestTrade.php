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
    ) {
    }

    public function returnPct(): float
    {
        return $this->entryPrice > 0 ? (($this->exitPrice - $this->entryPrice) / $this->entryPrice) * 100 : 0.0;
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
