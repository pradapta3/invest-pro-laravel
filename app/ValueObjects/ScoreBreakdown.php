<?php

namespace App\ValueObjects;

/**
 * Composite AI score (0-100) split into its four contributing factors,
 * mirroring the $score_breakdown array built inline in detail.php.
 */
final readonly class ScoreBreakdown
{
    public function __construct(
        public int $trend,
        public int $momentum,
        public int $flow,
        public int $fundamental,
    ) {
    }

    public function total(): int
    {
        return max(0, min(100, $this->trend + $this->momentum + $this->flow + $this->fundamental));
    }

    public function verdict(): string
    {
        $weights = config('screener.score');
        $total = $this->total();

        return match (true) {
            $total >= $weights['verdict_strong_buy'] => 'STRONG BUY',
            $total >= $weights['verdict_buy'] => 'BUY',
            $total >= $weights['verdict_neutral'] => 'NEUTRAL',
            default => 'AVOID',
        };
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'trend' => $this->trend,
            'momentum' => $this->momentum,
            'flow' => $this->flow,
            'fundamental' => $this->fundamental,
            'total' => $this->total(),
            'verdict' => $this->verdict(),
        ];
    }
}
