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
        /** null when the stop is not below the entry, so no ratio exists. */
        public ?float $riskRewardRatio,
        public ?float $takeProfit2 = null,
    ) {
    }

    /**
     * "1 : 2.4", or "-" when there is no ratio to state.
     */
    public function riskRewardText(): string
    {
        return $this->riskRewardRatio === null ? '-' : '1 : '.$this->riskRewardRatio;
    }

    /**
     * A plan that risks more than it targets. At 1:0.3 — which is what the
     * pivot rule produced before the R2 escalation — a trade needs to win 77%
     * of the time merely to break even, so this is worth saying out loud
     * rather than leaving the reader to divide two numbers.
     */
    public function risksMoreThanItTargets(): bool
    {
        return $this->riskRewardRatio !== null && $this->riskRewardRatio < 1.0;
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
