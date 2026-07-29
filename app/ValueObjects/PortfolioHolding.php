<?php

namespace App\ValueObjects;

use App\Models\UserPortfolio;

/**
 * One enriched portfolio row (position + live valuation), replacing the
 * mutated-in-place associative array portfolio.php built while looping
 * over the user_portfolio query result.
 */
final readonly class PortfolioHolding
{
    public function __construct(
        public UserPortfolio $position,
        public ?string $companyName,
        public float $currentPrice,
        public float $marketValue,
        public float $profitLoss,
        public float $profitLossPct,
        public bool $isLivePrice,
    ) {
    }
}
