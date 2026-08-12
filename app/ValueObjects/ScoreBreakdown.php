<?php

namespace App\ValueObjects;

/**
 * Composite AI score (0-100) split into its four contributing factors,
 * mirroring the $score_breakdown array built inline in detail.php.
 */
final readonly class ScoreBreakdown
{
    /**
     * Floats, not ints: the awards are graded, so a component lands anywhere
     * in its range rather than on a multiple of five. total() is what gets
     * shown, and that is still a whole number.
     */
    public function __construct(
        public float $trend,
        public float $momentum,
        public float $flow,
        public float $fundamental,
    ) {
    }

    public function total(): int
    {
        return max(0, min(100, (int) round($this->trend + $this->momentum + $this->flow + $this->fundamental)));
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
