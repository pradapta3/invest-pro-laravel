<?php

namespace App\Services;

use App\Models\StockPrice;
use App\Models\StockRef;
use App\ValueObjects\ProphetForecast;
use App\ValueObjects\ScoreBreakdown;
use App\ValueObjects\TradingPlan;

/**
 * All technical-indicator math and scoring/trading-plan rules for the app,
 * consolidated from what were three drifted, duplicated implementations in
 * the legacy code:
 *
 *  - lib_indicators.php's `TraderMath` (EMA, RSI, StochRSI, Supertrend,
 *    ADX, MFI) — SMA-seeded EMA.
 *  - indicators.php's `TechnicalAnalysis` (SMA, MACD, Stochastic, a
 *    *second*, differently-indexed RSI implementation, candlestick
 *    patterns) — first-value-seeded EMA.
 *  - Six near-identical calculateScore()/getTradingPlan() copies spread
 *    across functions.php, index.php, detail.php, telegram_bot.php,
 *    webhook.php and titan_scan.php, with take-profit/stop-loss
 *    percentages that had quietly drifted between copies.
 *
 * Where two legacy implementations genuinely disagreed (e.g. two RSI
 * algorithms, two EMA seeding conventions), one canonical version was
 * chosen rather than preserved side-by-side — see method docblocks below
 * for which legacy copy won and why. The richer trend/momentum/flow/
 * fundamental score breakdown from index.php/detail.php was kept as the
 * single canonical score (its weights now live in config/screener.php);
 * the older flat 50-point score used by the Telegram replies was folded
 * into this one.
 */
class TechnicalAnalysisService
{
    public function sma(array $data, int $period): float
    {
        if (count($data) < $period) {
            return 0.0;
        }

        $slice = array_slice($data, -$period);

        return array_sum($slice) / count($slice);
    }

    /**
     * Exponential moving average, seeded with the first data point (the
     * convention indicators.php used for MACD). Returns a full-length
     * array aligned to $data's indices.
     *
     * @return array<int, float>
     */
    public function ema(array $data, int $period): array
    {
        if (empty($data)) {
            return [];
        }

        $multiplier = 2 / ($period + 1);
        $ema = [0 => $data[array_key_first($data)]];
        $values = array_values($data);
        $ema = [0 => $values[0]];

        for ($i = 1; $i < count($values); $i++) {
            $ema[$i] = ($values[$i] * $multiplier) + ($ema[$i - 1] * (1 - $multiplier));
        }

        return $ema;
    }

    /**
     * Wilder's RSI, returned as a full history aligned to $closes (sparse
     * before `$period`), so callers needing a rolling window (StochRSI)
     * can use it directly. Use rsi() below when only the latest value
     * matters.
     *
     * @return array<int, float>
     */
    public function rsiSeries(array $closes, int $period = 14): array
    {
        $closes = array_values($closes);
        $count = count($closes);

        if ($count < $period + 1) {
            return [];
        }

        $changes = [];
        for ($i = 1; $i < $count; $i++) {
            $changes[$i] = $closes[$i] - $closes[$i - 1];
        }

        $avgGain = 0.0;
        $avgLoss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            if ($changes[$i] > 0) {
                $avgGain += $changes[$i];
            } else {
                $avgLoss += abs($changes[$i]);
            }
        }
        $avgGain /= $period;
        $avgLoss /= $period;

        $rsi = [];
        $rsi[$period] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + ($avgGain / $avgLoss)));

        for ($i = $period + 1; $i < $count; $i++) {
            $gain = $changes[$i] > 0 ? $changes[$i] : 0;
            $loss = $changes[$i] < 0 ? abs($changes[$i]) : 0;
            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;

            $rsi[$i] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
        }

        return $rsi;
    }

    public function rsi(array $closes, int $period = 14): float
    {
        $series = $this->rsiSeries($closes, $period);

        return empty($series) ? 50.0 : (float) end($series);
    }

    /**
     * Stochastic RSI (%K smoothed to %D over 3 periods).
     *
     * @return array{k: array<int, float>, d: array<int, float>}
     */
    public function stochasticRsi(array $closes, int $period = 14): array
    {
        $rsi = $this->rsiSeries($closes, $period);
        $stochRsi = [];
        $smoothD = [];

        $indices = array_keys($rsi);
        if (empty($indices)) {
            return ['k' => [], 'd' => []];
        }

        foreach ($rsi as $i => $value) {
            $slice = array_slice($rsi, max(0, array_search($i, $indices) - $period + 1), $period);
            if (count($slice) < $period) {
                continue;
            }
            $min = min($slice);
            $max = max($slice);
            $stochRsi[$i] = $max - $min == 0 ? 0.0 : (($value - $min) / ($max - $min)) * 100;
        }

        $stochIndices = array_keys($stochRsi);
        foreach ($stochIndices as $pos => $i) {
            if ($pos < 2) {
                continue;
            }
            $prev1 = $stochRsi[$stochIndices[$pos - 1]];
            $prev2 = $stochRsi[$stochIndices[$pos - 2]];
            $smoothD[$i] = ($stochRsi[$i] + $prev1 + $prev2) / 3;
        }

        return ['k' => $stochRsi, 'd' => $smoothD];
    }

    /**
     * Fast %K stochastic oscillator over the close/high/low arrays.
     * %D is simplified to equal %K (matches the legacy implementation —
     * a true 3-period SMA of %K was never wired up in the original app).
     *
     * @return array{k: float, d: float}
     */
    public function stochastic(array $closes, array $highs, array $lows, int $period = 14): array
    {
        $idx = count($closes) - 1;

        if ($idx < $period) {
            return ['k' => 50.0, 'd' => 50.0];
        }

        $highestHigh = max(array_slice($highs, -$period));
        $lowestLow = min(array_slice($lows, -$period));
        $currentClose = $closes[$idx];

        if (($highestHigh - $lowestLow) == 0) {
            return ['k' => 50.0, 'd' => 50.0];
        }

        $k = (($currentClose - $lowestLow) / ($highestHigh - $lowestLow)) * 100;

        return ['k' => $k, 'd' => $k];
    }

    /**
     * MACD (12, 26, 9) using the first-value-seeded EMA convention.
     *
     * @return array{macd: float, signal: float, hist: float}
     */
    public function macd(array $closes): array
    {
        if (count($closes) < 26) {
            return ['macd' => 0.0, 'signal' => 0.0, 'hist' => 0.0];
        }

        $ema12 = $this->ema($closes, 12);
        $ema26 = $this->ema($closes, 26);

        $macdLine = [];
        foreach (array_keys($ema12) as $i) {
            if (isset($ema26[$i])) {
                $macdLine[$i] = $ema12[$i] - $ema26[$i];
            }
        }

        $signalLine = $this->ema(array_values($macdLine), 9);

        $lastMacd = (float) end($macdLine);
        $lastSignal = (float) end($signalLine);

        return [
            'macd' => $lastMacd,
            'signal' => $lastSignal,
            'hist' => $lastMacd - $lastSignal,
        ];
    }

    /**
     * @return array{pivot: float, r1: float, r2: float, s1: float, s2: float}
     */
    public function pivotPoints(float $high, float $low, float $close): array
    {
        $pivot = ($high + $low + $close) / 3;

        return [
            'pivot' => $pivot,
            'r1' => (2 * $pivot) - $low,
            's1' => (2 * $pivot) - $high,
            'r2' => $pivot + ($high - $low),
            's2' => $pivot - ($high - $low),
        ];
    }

    /**
     * Supertrend (ATR-based) trend-following overlay. Not currently wired
     * into a screener, ported from lib_indicators.php's TraderMath for
     * parity/future use.
     *
     * @return array{value: array<int, float>, trend: array<int, int>}
     */
    public function supertrend(array $highs, array $lows, array $closes, int $period = 10, float $multiplier = 3): array
    {
        $tr = [0 => $highs[0] - $lows[0]];
        for ($i = 1; $i < count($closes); $i++) {
            $tr[$i] = max($highs[$i] - $lows[$i], abs($highs[$i] - $closes[$i - 1]), abs($lows[$i] - $closes[$i - 1]));
        }

        $atr = [$period - 1 => array_sum(array_slice($tr, 0, $period)) / $period];
        for ($i = $period; $i < count($closes); $i++) {
            $atr[$i] = (($atr[$i - 1] * ($period - 1)) + $tr[$i]) / $period;
        }

        $st = [];
        $dir = [];
        $ub = [];
        $lb = [];

        for ($i = $period; $i < count($closes); $i++) {
            $mid = ($highs[$i] + $lows[$i]) / 2;
            $basicUb = $mid + ($multiplier * $atr[$i]);
            $basicLb = $mid - ($multiplier * $atr[$i]);

            if ($i == $period) {
                $ub[$i] = $basicUb;
                $lb[$i] = $basicLb;
                $dir[$i] = 1;

                continue;
            }

            $ub[$i] = ($basicUb < $ub[$i - 1] || $closes[$i - 1] > $ub[$i - 1]) ? $basicUb : $ub[$i - 1];
            $lb[$i] = ($basicLb > $lb[$i - 1] || $closes[$i - 1] < $lb[$i - 1]) ? $basicLb : $lb[$i - 1];

            $dir[$i] = $dir[$i - 1];
            if ($dir[$i] == 1 && $closes[$i] < $lb[$i]) {
                $dir[$i] = -1;
            }
            if ($dir[$i] == -1 && $closes[$i] > $ub[$i]) {
                $dir[$i] = 1;
            }

            $st[$i] = $dir[$i] == 1 ? $lb[$i] : $ub[$i];
        }

        return ['value' => $st, 'trend' => $dir];
    }

    /**
     * Average Directional Index (trend strength, 0-100). Ported from
     * lib_indicators.php's TraderMath for parity/future use.
     *
     * @return array<int, float>
     */
    public function adx(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $tr = [];
        $dmPlus = [];
        $dmMinus = [];

        for ($i = 1; $i < count($closes); $i++) {
            $tr[$i] = max($highs[$i] - $lows[$i], abs($highs[$i] - $closes[$i - 1]), abs($lows[$i] - $closes[$i - 1]));
            $dmPlus[$i] = ($highs[$i] - $highs[$i - 1] > $lows[$i - 1] - $lows[$i]) ? max($highs[$i] - $highs[$i - 1], 0) : 0;
            $dmMinus[$i] = ($lows[$i - 1] - $lows[$i] > $highs[$i] - $highs[$i - 1]) ? max($lows[$i - 1] - $lows[$i], 0) : 0;
        }

        $trSmoothed = [$period => array_sum(array_slice($tr, 1, $period))];
        $dmPlusSmoothed = [$period => array_sum(array_slice($dmPlus, 1, $period))];
        $dmMinusSmoothed = [$period => array_sum(array_slice($dmMinus, 1, $period))];

        $dx = [];
        $adx = [];

        for ($i = $period + 1; $i < count($closes); $i++) {
            $trSmoothed[$i] = $trSmoothed[$i - 1] - ($trSmoothed[$i - 1] / $period) + $tr[$i];
            $dmPlusSmoothed[$i] = $dmPlusSmoothed[$i - 1] - ($dmPlusSmoothed[$i - 1] / $period) + $dmPlus[$i];
            $dmMinusSmoothed[$i] = $dmMinusSmoothed[$i - 1] - ($dmMinusSmoothed[$i - 1] / $period) + $dmMinus[$i];

            $diPlus = 100 * ($dmPlusSmoothed[$i] / $trSmoothed[$i]);
            $diMinus = 100 * ($dmMinusSmoothed[$i] / $trSmoothed[$i]);
            $sum = $diPlus + $diMinus;
            $dx[$i] = $sum == 0 ? 0 : 100 * abs($diPlus - $diMinus) / $sum;
        }

        if (isset($dx[$period * 2])) {
            $adx[$period * 2] = array_sum(array_slice($dx, $period + 1, $period)) / $period;
            for ($i = ($period * 2) + 1; $i < count($closes); $i++) {
                $adx[$i] = (($adx[$i - 1] * ($period - 1)) + $dx[$i]) / $period;
            }
        }

        return $adx;
    }

    /**
     * Money Flow Index (volume-weighted RSI, 0-100). Ported from
     * lib_indicators.php's TraderMath for parity/future use.
     *
     * @return array<int, float>
     */
    public function mfi(array $highs, array $lows, array $closes, array $volumes, int $period = 14): array
    {
        $mfi = [];

        for ($i = $period; $i < count($closes); $i++) {
            $positive = 0.0;
            $negative = 0.0;

            for ($j = $i - $period + 1; $j <= $i; $j++) {
                $typicalPrice = ($highs[$j] + $lows[$j] + $closes[$j]) / 3;
                $prevTypicalPrice = ($highs[$j - 1] + $lows[$j - 1] + $closes[$j - 1]) / 3;

                if ($typicalPrice > $prevTypicalPrice) {
                    $positive += $typicalPrice * $volumes[$j];
                } elseif ($typicalPrice < $prevTypicalPrice) {
                    $negative += $typicalPrice * $volumes[$j];
                }
            }

            $mfi[$i] = $negative == 0.0 ? 100.0 : 100 - (100 / (1 + ($positive / $negative)));
        }

        return $mfi;
    }

    public function detectCandlestickPattern(float $open, float $high, float $low, float $close, float $prevOpen, float $prevClose): string
    {
        $body = abs($close - $open);
        $upperShadow = $high - max($open, $close);
        $lowerShadow = min($open, $close) - $low;

        if ($lowerShadow > ($body * 2) && $upperShadow < ($body * 0.5)) {
            return 'Hammer';
        }

        if ($prevClose < $prevOpen && $close > $open && $close > $prevOpen && $open < $prevClose) {
            return 'Bullish Engulfing';
        }

        if ($body <= ($close * 0.001)) {
            return 'Doji';
        }

        return '';
    }

    public function standardDeviation(array $values, bool $sample = false): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $sumSquaredDiff = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values));

        if ($sample && $n > 1) {
            $n--;
        }

        return sqrt($sumSquaredDiff / $n);
    }

    /**
     * Simple linear regression over an evenly-spaced series (index = x).
     *
     * @return array{slope: float, intercept: float, forecast: float} forecast is the value predicted one step past the series
     */
    public function linearRegression(array $values): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0.0, 'intercept' => (float) ($values[0] ?? 0), 'forecast' => (float) ($values[0] ?? 0)];
        }

        $x = range(1, $n);
        $y = array_values($values);

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXX = 0.0;
        $sumXY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sumXX += $x[$i] * $x[$i];
            $sumXY += $x[$i] * $y[$i];
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denominator == 0.0 ? 0.0 : (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'forecast' => ($slope * ($n + 1)) + $intercept,
        ];
    }

    /**
     * "Prophet" forecast: linear-regression trend + volatility-based
     * support/resistance band, replacing bot_loop.php's calculateTrend().
     * Uses at most the trailing 30 closes, as the legacy code did.
     */
    public function prophetTrend(array $closes): ?ProphetForecast
    {
        $closes = array_values($closes);
        if (count($closes) > 30) {
            $closes = array_slice($closes, -30);
        }

        if (count($closes) < 5) {
            return null;
        }

        $regression = $this->linearRegression($closes);
        $slope = $regression['slope'];
        $stdDev = $this->standardDeviation($closes);
        $rsi = $this->rsi($closes);
        $lastPrice = (float) end($closes);

        [$status, $strength] = match (true) {
            $slope > 5 => ['STRONG UPTREND', 'Strong'],
            $slope > 0 => ['UPTREND', 'Moderate'],
            $slope < -5 => ['STRONG DOWNTREND', 'Strong'],
            $slope < 0 => ['DOWNTREND', 'Weak'],
            default => ['SIDEWAYS', 'Weak'],
        };

        $upperBound = $regression['forecast'] + ($stdDev * 1.5);
        $lowerBound = $regression['forecast'] - ($stdDev * 1.5);

        return new ProphetForecast(
            lastPrice: $lastPrice,
            slope: round($slope, 2),
            standardDeviation: round($stdDev, 2),
            rsi: round($rsi),
            status: $status,
            strength: $strength,
            forecast: round($regression['forecast']),
            support: round($lowerBound),
            resistance: round($upperBound),
        );
    }

    /**
     * Pearson correlation coefficient between two equal-length price
     * series — the "Ghost Pattern" similarity search.
     */
    public function pearsonCorrelation(array $x, array $y): float
    {
        $n = min(count($x), count($y));
        if ($n === 0) {
            return 0.0;
        }

        $x = array_slice(array_values($x), 0, $n);
        $y = array_slice(array_values($y), 0, $n);

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumX2 = 0.0;
        $sumY2 = 0.0;
        $sumXY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
            $sumXY += $x[$i] * $y[$i];
        }

        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));

        return $denominator == 0.0 ? 0.0 : $numerator / $denominator;
    }

    /**
     * The canonical composite AI score (0-100), replacing every
     * calculateScore() copy in the legacy app. Weights come from
     * config('screener.score').
     */
    public function calculateScore(StockPrice $price, ?StockRef $ref = null): ScoreBreakdown
    {
        $w = config('screener.score');

        $close = (float) $price->close_price;
        $ma20 = (float) $price->ma20;
        $vwap = (float) $price->vwap;
        $rsi = (float) $price->rsi_14;
        $stochK = (float) $price->stoch_k;
        $macdHist = (float) $price->macd_hist;

        // Every indicator below is only scored when it is actually present.
        // Absent ones are stored as 0, not null, so an unguarded comparison
        // reads a missing figure as a favourable one: `close > ma20` is true
        // against a missing MA20, and `stoch_k < 20` is true against a missing
        // stochastic. Measured over the whole exchange before these guards
        // went in, 19% of every score was awarded for data that did not
        // exist. VWAP was already guarded here; the rest were not.
        $trend = 0;
        if ($ma20 > 0 && $close > $ma20) {
            $trend += $w['trend_above_ma20'];
        }
        if ($vwap > 0 && $close > $vwap) {
            $trend += $w['trend_above_vwap'];
        }

        $momentum = 0;
        if ($macdHist > 0) {
            $momentum += $w['momentum_macd_positive'];
        }
        if ($rsi > 0) {
            if ($rsi >= $w['rsi_sweet_spot_min'] && $rsi <= $w['rsi_sweet_spot_max']) {
                $momentum += $w['momentum_rsi_sweet_spot'];
            } elseif ($rsi < $w['rsi_oversold_max']) {
                // Oversold only. This used to read `$rsi > 70 || $rsi < 30`,
                // paying the same credit for both ends, so a stock at RSI 85
                // scored above one at RSI 45 — on a composite whose top band
                // is labelled STRONG BUY. Overbought is the risk this score
                // should be flagging, not rewarding, so it now earns nothing.
                $momentum += $w['momentum_rsi_oversold'];
            }
        }
        if ($stochK > 0 && $stochK < $w['stoch_oversold_max']) {
            $momentum += $w['momentum_stoch_oversold'];
        }

        $flow = 0;
        $volAvg20 = (int) $price->vol_avg_20;
        if ($volAvg20 > 0) {
            $ratio = (int) $price->volume / $volAvg20;
            if ($ratio > $w['volume_above_avg_ratio']) {
                $flow += $w['flow_volume_above_avg'];
            }
            if ($ratio > $w['volume_spike_ratio']) {
                $flow += $w['flow_volume_spike'];
            }
        }
        if ($price->is_breakout) {
            $flow += $w['flow_breakout'];
        }

        $fundamental = 0;
        $roe = (float) ($ref?->roe ?? 0);
        $der = (float) ($ref?->der ?? 0);
        $per = (float) ($ref?->pe_ratio ?? 0);
        if ($roe > $w['fundamental_roe_min']) {
            $fundamental += $w['fundamental_roe'];
        }
        // Lower gearing is better, but only down to zero. A negative DER means
        // negative equity — liabilities exceeding assets — which is the worst
        // reading there is, and `$der < 1.5` scored it as the best. The old
        // `?? 10` default was there to make a missing value fail that test;
        // requiring a positive figure says the same thing without the
        // sentinel, and catches a stored 0 as well, which the sentinel missed.
        if ($der > 0 && $der < $w['fundamental_der_max']) {
            $fundamental += $w['fundamental_der'];
        }
        if ($per > 0 && $per < $w['fundamental_per_max']) {
            $fundamental += $w['fundamental_per'];
        }

        return new ScoreBreakdown($trend, $momentum, $flow, $fundamental);
    }

    /**
     * Canonical entry/target/stop plan, replacing every getTradingPlan()
     * copy. $strategy selects the multiplier set from
     * config('screener.trading_plan'): "swing" (pivot-point based,
     * default), "bsjp" (fixed % overnight plan) or "titan" (fixed %
     * momentum plan with two take-profit legs).
     */
    public function buildTradingPlan(StockPrice $price, string $strategy = 'swing'): TradingPlan
    {
        $close = (float) $price->close_price;
        $high = (float) $price->high_price;
        $low = (float) $price->low_price;
        $ma20 = (float) $price->ma20;
        $cfg = config("screener.trading_plan.{$strategy}") ?? config('screener.trading_plan.swing');

        if ($strategy === 'bsjp') {
            $takeProfit = $close * (1 + $cfg['take_profit_pct']);
            $stopLoss = $close * (1 - $cfg['stop_loss_pct']);

            return $this->finalizePlan($close, $close, $close, $takeProfit, $stopLoss);
        }

        if ($strategy === 'titan') {
            $stopLoss = $close * (1 - $cfg['stop_loss_pct']);
            $takeProfit1 = $close * (1 + $cfg['take_profit_1_pct']);
            $takeProfit2 = $close * (1 + $cfg['take_profit_2_pct']);

            return $this->finalizePlan($close, $close, $close, $takeProfit1, $stopLoss, $takeProfit2);
        }

        // "swing": pivot-point based plan.
        $pivots = $this->pivotPoints($high, $low, $close);
        $buyPrice = $close > $ma20 ? $close : $pivots['s1'];
        $entryLow = $buyPrice * (1 - $cfg['entry_band_pct']);
        $entryHigh = $buyPrice * (1 + $cfg['entry_band_pct']);
        $stopLoss = $pivots['s1'] * (1 - $cfg['stop_loss_buffer_pct']);

        return $this->finalizePlan($buyPrice, $entryLow, $entryHigh, $pivots['r1'], $stopLoss);
    }

    private function finalizePlan(float $buyPrice, float $entryLow, float $entryHigh, float $takeProfit, float $stopLoss, ?float $takeProfit2 = null): TradingPlan
    {
        $reward = $takeProfit - $buyPrice;
        $risk = $buyPrice - $stopLoss;
        $rrr = $risk > 0 ? round($reward / $risk, 1) : 0.0;

        return new TradingPlan($entryLow, $entryHigh, $takeProfit, $stopLoss, $rrr, $takeProfit2);
    }

    /**
     * MA20 crossover backtest over a close-price series (buy when price
     * crosses above MA20, exit on a cross below MA20 or a 5% stop-loss),
     * replacing the inline simulation in detail.php's "3-Month Backtest"
     * widget. 0.15%/0.25% match the live buy/sell fee config.
     *
     * @return array{trades: int, win_rate: float, roi_pct: float, holding: bool}
     */
    public function backtestMa20Strategy(array $closes, float $startingBalance = 10_000_000): array
    {
        $closes = array_values($closes);
        if (count($closes) <= 20) {
            return ['trades' => 0, 'win_rate' => 0.0, 'roi_pct' => 0.0, 'holding' => false];
        }

        $balance = $startingBalance;
        $shares = 0.0;
        $inPosition = false;
        $entryPrice = 0.0;
        $trades = 0;
        $wins = 0;
        $balanceBeforeTrade = $balance;

        $buyFee = 1 + config('trading.fees.buy_rate');
        $sellFee = 1 - config('trading.fees.sell_rate');

        for ($i = 20; $i < count($closes); $i++) {
            $price = $closes[$i];
            $ma20 = array_sum(array_slice($closes, $i - 20, 20)) / 20;

            if (! $inPosition && $price > $ma20 && $closes[$i - 1] <= $ma20) {
                $shares = floor($balance / $price);
                $balance -= $shares * $price * $buyFee;
                $inPosition = true;
                $entryPrice = $price;
                $balanceBeforeTrade = $balance + ($shares * $price);
            } elseif ($inPosition && ($price < $ma20 || $price < $entryPrice * 0.95)) {
                $balance += $shares * $price * $sellFee;
                $trades++;
                if ($balance > $balanceBeforeTrade) {
                    $wins++;
                }
                $inPosition = false;
                $shares = 0;
            }
        }

        if ($inPosition) {
            $balance += $shares * end($closes);
        }

        return [
            'trades' => $trades,
            'win_rate' => $trades > 0 ? round(($wins / $trades) * 100) : 0.0,
            'roi_pct' => round((($balance - $startingBalance) / $startingBalance) * 100, 1),
            'holding' => $inPosition,
        ];
    }
}
