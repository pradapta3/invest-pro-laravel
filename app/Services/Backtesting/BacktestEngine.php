<?php

namespace App\Services\Backtesting;

use App\Models\StockPriceHistory;
use App\Services\TechnicalAnalysisService;
use App\ValueObjects\BacktestResult;
use App\ValueObjects\BacktestTrade;
use App\ValueObjects\WalkForwardReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Replays StockScreenerService's rule-based strategies against stored
 * historical OHLCV (stock_price_histories, see BackfillPriceHistory) to
 * find out whether they've actually had an edge, instead of trusting
 * config/screener.php's thresholds on faith.
 *
 * Scope, deliberately:
 *
 *  - Only price/volume-driven strategies can be tested — magic_formula,
 *    undervalued, top_picks and value_investing all depend on ROE/PER/
 *    PBV/DER, and stock_refs only ever holds *today's* snapshot of those,
 *    not a history. Backtesting them against past dates would silently
 *    apply today's fundamentals to yesterday's price action (look-ahead
 *    bias) and produce numbers that flatter the strategy for a reason
 *    that has nothing to do with whether it actually works. Better to
 *    refuse than to fake it — see SUPPORTED_STRATEGIES.
 *  - Daily OHLCV has no real intraday VWAP, so "vwap" here is the
 *    standard (high+low+close)/3 typical-price proxy, not the real
 *    volume-weighted average the live scanner gets from TradingView.
 *  - See BacktestResult's docblock for what the performance metrics do
 *    and don't model (no capital constraints, one position per ticker
 *    at a time, equal-stake-per-trade accounting).
 */
class BacktestEngine
{
    public const SUPPORTED_STRATEGIES = ['bsjp', 'titan', 'trend_runner', 'pullback_sniper', 'bandar_radar'];

    public const UNSUPPORTED_STRATEGY_REASON = "depends on fundamentals (ROE/PER/PBV/DER) stock_refs only stores a current snapshot of, not a history — backtesting it would apply today's fundamentals to past dates.";

    /** Bars of history required before a strategy is evaluated (MACD needs 26 + buffer). */
    private const MIN_LOOKBACK = 30;

    /** Cap on a swing-style trade's simulated holding period. */
    private const MAX_HOLDING_DAYS = 20;

    public function __construct(private readonly TechnicalAnalysisService $ta)
    {
    }

    /**
     * @param  Collection<int, string>|null  $tickers  defaults to every ticker with stored history
     */
    public function run(string $strategy, Carbon $from, Carbon $to, ?Collection $tickers = null): BacktestResult
    {
        $this->assertSupported($strategy);

        $cfg = config('screener');
        $tickers ??= StockPriceHistory::query()->distinct()->pluck('ticker');

        $allTrades = collect();
        foreach ($tickers as $ticker) {
            $allTrades = $allTrades->merge($this->simulateTicker($ticker, $strategy, $from, $to, $cfg));
        }

        return new BacktestResult($strategy, $from->copy(), $to->copy(), $allTrades);
    }

    /**
     * Runs the same strategy independently across sequential yearly
     * sub-periods so a lucky single stretch can't hide behind a flattering
     * aggregate number — see WalkForwardReport::isConsistent().
     *
     * @param  Collection<int, string>|null  $tickers
     */
    public function runWalkForward(string $strategy, Carbon $from, Carbon $to, ?Collection $tickers = null): WalkForwardReport
    {
        $this->assertSupported($strategy);

        $periods = collect();
        $cursor = $from->copy();

        while ($cursor->lt($to)) {
            $periodEnd = $cursor->copy()->addYear()->subDay();
            if ($periodEnd->gt($to)) {
                $periodEnd = $to->copy();
            }

            $periods->push($this->run($strategy, $cursor->copy(), $periodEnd, $tickers));

            $cursor = $periodEnd->copy()->addDay();
        }

        return new WalkForwardReport($strategy, $periods, $this->run($strategy, $from, $to, $tickers));
    }

    private function assertSupported(string $strategy): void
    {
        if (! in_array($strategy, self::SUPPORTED_STRATEGIES, true)) {
            throw new InvalidArgumentException("Can't backtest '{$strategy}': ".self::UNSUPPORTED_STRATEGY_REASON);
        }
    }

    /**
     * @return Collection<int, BacktestTrade>
     */
    private function simulateTicker(string $ticker, string $strategy, Carbon $from, Carbon $to, array $cfg): Collection
    {
        $bars = StockPriceHistory::query()
            ->where('ticker', $ticker)
            ->orderBy('date')
            ->get(['date', 'open', 'high', 'low', 'close', 'volume']);

        if ($bars->count() < self::MIN_LOOKBACK + 5) {
            return collect();
        }

        $dates = $bars->map(fn ($b) => Carbon::parse($b->date))->values()->all();
        $opens = $bars->map(fn ($b) => (float) $b->open)->values()->all();
        $highs = $bars->map(fn ($b) => (float) $b->high)->values()->all();
        $lows = $bars->map(fn ($b) => (float) $b->low)->values()->all();
        $closes = $bars->map(fn ($b) => (float) $b->close)->values()->all();
        $volumes = $bars->map(fn ($b) => (int) $b->volume)->values()->all();

        $trades = collect();
        $n = count($closes);
        $cooldownUntil = -1;

        for ($i = self::MIN_LOOKBACK; $i < $n; $i++) {
            if ($i <= $cooldownUntil) {
                continue;
            }

            $date = $dates[$i];
            if ($date->lt($from) || $date->gt($to)) {
                continue;
            }

            if (! $this->entrySignal($strategy, $opens, $highs, $lows, $closes, $volumes, $i, $cfg)) {
                continue;
            }

            $entryIndex = $i;
            [$exitIndex, $exitPrice, $reason] = $this->simulateExit($strategy, $opens, $highs, $lows, $closes, $entryIndex, $cfg);

            $trades->push(new BacktestTrade(
                ticker: $ticker,
                entryDate: $dates[$entryIndex],
                entryPrice: $closes[$entryIndex],
                exitDate: $dates[$exitIndex],
                exitPrice: $exitPrice,
                exitReason: $reason,
                holdingDays: $exitIndex - $entryIndex,
                buyFeeRate: (float) config('trading.fees.buy_rate'),
                sellFeeRate: (float) config('trading.fees.sell_rate'),
            ));

            $cooldownUntil = $exitIndex;
        }

        return $trades;
    }

    private function entrySignal(string $strategy, array $opens, array $highs, array $lows, array $closes, array $volumes, int $i, array $cfg): bool
    {
        $close = $closes[$i];

        if ($close <= $cfg['baseline']['min_price']) {
            return false;
        }

        $closesToDate = array_slice($closes, 0, $i + 1);
        $volumesToDate = array_slice($volumes, 0, $i + 1);
        $ma20 = $this->ta->sma($closesToDate, 20);
        $rsi14 = $this->ta->rsi($closesToDate, 14);
        $volAvg20 = $this->ta->sma($volumesToDate, 20);
        $volumeSpikeRatio = $volAvg20 > 0 ? $volumes[$i] / $volAvg20 : 0.0;
        $valueTransaction = $close * $volumes[$i];

        return match ($strategy) {
            'bsjp' => $close > $opens[$i]
                && $close >= $highs[$i] * $cfg['bsjp']['min_close_vs_high_ratio']
                && $volumeSpikeRatio > $cfg['bsjp']['min_volume_spike_ratio']
                && $valueTransaction > $cfg['bsjp']['min_transaction_value'],

            'bandar_radar' => $volumeSpikeRatio > $cfg['bandar_radar']['min_volume_spike_ratio']
                && $valueTransaction > $cfg['bandar_radar']['min_transaction_value'],

            'trend_runner' => $close > $ma20
                && $rsi14 > $cfg['trend_runner']['min_rsi']
                && $rsi14 < $cfg['trend_runner']['max_rsi'],

            'pullback_sniper' => $close > $ma20
                && $rsi14 >= $cfg['pullback_sniper']['min_rsi']
                && $rsi14 <= $cfg['pullback_sniper']['max_rsi']
                && $valueTransaction > $cfg['pullback_sniper']['min_transaction_value'],

            'titan' => $this->titanScore($closesToDate, array_slice($highs, 0, $i + 1), array_slice($lows, 0, $i + 1), $volumesToDate, $cfg['titan']) !== null,

            default => false,
        };
    }

    /**
     * Mirrors StockScreenerService::titan()'s scoring exactly (same
     * config thresholds), evaluated at one point in time instead of
     * "right now". Returns the score if it qualifies, null otherwise.
     */
    private function titanScore(array $closes, array $highs, array $lows, array $volumes, array $titanCfg): ?int
    {
        $i = count($closes) - 1;
        $close = $closes[$i];
        $high = $highs[$i];
        $low = $lows[$i];
        $volume = $volumes[$i];

        if ($close < $titanCfg['min_price']) {
            return null;
        }
        if (($close * $volume) < $titanCfg['min_transaction_value']) {
            return null;
        }

        $volAvg20 = $this->ta->sma($volumes, 20);
        $ratio = $volAvg20 > 0 ? $volume / $volAvg20 : 0.0;

        $tierPoints = null;
        foreach ($titanCfg['volume_spike_tiers'] as $tier) {
            if ($ratio >= $tier['ratio']) {
                $tierPoints = $tier['points'];

                break;
            }
        }
        if ($tierPoints === null) {
            return null;
        }

        $score = $tierPoints;
        $ma20 = $this->ta->sma($closes, 20);
        $vwapProxy = ($high + $low + $close) / 3;

        if ($close > $ma20) {
            $score += $titanCfg['trend_points'];
        }
        if ($close > $vwapProxy) {
            $score += $titanCfg['vwap_points'];
        }

        if ($i >= 21) {
            $windowMax = max(array_slice($closes, $i - 20, 20));
            if ($close > $windowMax && $closes[$i - 1] <= $windowMax) {
                $score += $titanCfg['breakout_points'];
            }
        }

        if ($this->ta->macd($closes)['hist'] > 0) {
            $score += $titanCfg['macd_points'];
        }

        $rsi = $this->ta->rsi($closes, 14);
        if ($rsi >= $titanCfg['rsi_min'] && $rsi <= $titanCfg['rsi_max']) {
            $score += $titanCfg['rsi_points'];
        }

        return $score >= $titanCfg['qualify_score'] ? $score : null;
    }

    /**
     * @return array{0: int, 1: float, 2: string} [exitIndex, exitPrice, reason]
     */
    private function simulateExit(string $strategy, array $opens, array $highs, array $lows, array $closes, int $entryIndex, array $cfg): array
    {
        $n = count($closes);

        if ($strategy === 'bsjp') {
            // "Beli sore, jual pagi" — one-day hold, exit at next bar's open.
            $exitIndex = min($entryIndex + 1, $n - 1);
            $hasNextBar = $exitIndex > $entryIndex;
            $exitPrice = $hasNextBar && $opens[$exitIndex] > 0 ? $opens[$exitIndex] : $closes[$exitIndex];

            return [$exitIndex, $exitPrice, $hasNextBar ? 'next_day_open' : 'end_of_data'];
        }

        [$takeProfitPrice, $stopLossPrice] = $this->bracketLevels($strategy, $entryIndex, $highs, $lows, $closes, $cfg);

        $lastIndex = min($entryIndex + self::MAX_HOLDING_DAYS, $n - 1);
        for ($i = $entryIndex + 1; $i <= $lastIndex; $i++) {
            if ($highs[$i] >= $takeProfitPrice) {
                return [$i, $takeProfitPrice, 'take_profit'];
            }
            if ($lows[$i] <= $stopLossPrice) {
                return [$i, $stopLossPrice, 'stop_loss'];
            }
        }

        $reason = $lastIndex >= $n - 1 ? 'end_of_data' : 'max_holding_days';

        return [$lastIndex, $closes[$lastIndex], $reason];
    }

    /**
     * @return array{0: float, 1: float} [takeProfitPrice, stopLossPrice]
     */
    private function bracketLevels(string $strategy, int $entryIndex, array $highs, array $lows, array $closes, array $cfg): array
    {
        $entryPrice = $closes[$entryIndex];

        if ($strategy === 'titan') {
            $plan = $cfg['trading_plan']['titan'];

            return [
                $entryPrice * (1 + $plan['take_profit_1_pct']),
                $entryPrice * (1 - $plan['stop_loss_pct']),
            ];
        }

        // trend_runner / pullback_sniper / bandar_radar: the same
        // pivot-point swing plan TechnicalAnalysisService::buildTradingPlan()
        // computes for the live dashboard.
        $pivots = $this->ta->pivotPoints($highs[$entryIndex], $lows[$entryIndex], $entryPrice);
        $swingCfg = $cfg['trading_plan']['swing'];

        return [
            $pivots['r1'],
            $pivots['s1'] * (1 - $swingCfg['stop_loss_buffer_pct']),
        ];
    }
}
