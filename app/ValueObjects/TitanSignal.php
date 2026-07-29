<?php

namespace App\ValueObjects;

use App\Models\StockPrice;

/**
 * A single Titan Volatility scanner result: a tiered momentum score plus
 * the tags that produced it. Replaces the ad-hoc keys titan_scan.php
 * bolted onto each row array ($row['titan_score'], $row['tier'], ...).
 */
final readonly class TitanSignal
{
    /**
     * @param  string[]  $tags
     */
    public function __construct(
        public StockPrice $price,
        public int $score,
        public string $tier,
        public array $tags,
        public float $volumeSpikeRatio,
        public TradingPlan $plan,
    ) {
    }

    public function tierIcon(): string
    {
        return match ($this->tier) {
            'S' => '💎',
            'A' => '🔥',
            default => '⚡',
        };
    }
}
