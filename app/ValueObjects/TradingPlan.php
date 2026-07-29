<?php

namespace App\ValueObjects;

/**
 * Immutable entry/target/stop plan produced by
 * TechnicalAnalysisService::buildTradingPlan(). Replaces the associative
 * arrays (['tp' => ..., 'sl' => ..., 'entry' => ..., 'rrr' => ...]) that
 * every legacy getTradingPlan() implementation returned.
 */
final readonly class TradingPlan
{
    public function __construct(
        public float $entryLow,
        public float $entryHigh,
        public float $takeProfit,
        public float $stopLoss,
        public float $riskRewardRatio,
        public ?float $takeProfit2 = null,
    ) {
    }

    public function entryText(): string
    {
        if ($this->entryLow <= 0.0 && $this->entryHigh <= 0.0) {
            return '-';
        }

        if (number_format($this->entryLow) === number_format($this->entryHigh)) {
            return number_format($this->entryLow);
        }

        return number_format($this->entryLow).'-'.number_format($this->entryHigh);
    }

    public function midEntry(): float
    {
        return ($this->entryLow + $this->entryHigh) / 2;
    }

    public function takeProfitPct(): float
    {
        $entry = $this->midEntry();

        return $entry > 0 ? round((($this->takeProfit - $entry) / $entry) * 100, 1) : 0.0;
    }

    public function stopLossPct(): float
    {
        $entry = $this->midEntry();

        return $entry > 0 ? round((($entry - $this->stopLoss) / $entry) * 100, 1) : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entry' => $this->entryText(),
            'entry_low' => $this->entryLow,
            'entry_high' => $this->entryHigh,
            'tp' => $this->takeProfit,
            'tp2' => $this->takeProfit2,
            'sl' => $this->stopLoss,
            'rrr' => $this->riskRewardRatio,
            'tp_pct' => $this->takeProfitPct(),
            'sl_pct' => $this->stopLossPct(),
        ];
    }
}
