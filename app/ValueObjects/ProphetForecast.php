<?php

namespace App\ValueObjects;

/**
 * Linear-regression + volatility forecast produced by
 * TechnicalAnalysisService::prophetTrend(). Replaces the associative array
 * built by bot_loop.php's calculateTrend().
 */
final readonly class ProphetForecast
{
    public function __construct(
        public float $lastPrice,
        public float $slope,
        public float $standardDeviation,
        public float $rsi,
        public string $status,
        public string $strength,
        public float $forecast,
        public float $support,
        public float $resistance,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'last_price' => $this->lastPrice,
            'slope' => $this->slope,
            'std_dev' => $this->standardDeviation,
            'rsi' => $this->rsi,
            'status' => $this->status,
            'strength' => $this->strength,
            'prediction' => $this->forecast,
            'support' => $this->support,
            'resistance' => $this->resistance,
        ];
    }
}
