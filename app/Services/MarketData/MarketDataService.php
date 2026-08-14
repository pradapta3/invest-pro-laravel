<?php

namespace App\Services\MarketData;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Single entry point for all external market-data access. Controllers,
 * console commands and other services should depend on this, never on
 * YahooFinanceClient / TradingViewScannerClient directly.
 */
class MarketDataService
{
    public function __construct(
        private readonly YahooFinanceClient $yahoo,
        private readonly TradingViewScannerClient $tradingView,
    ) {
    }

    /**
     * Normalized daily OHLCV series, newest point last.
     *
     * @return array{timestamps: int[], open: float[], high: float[], low: float[], close: float[], volume: int[], meta: array}
     */
    public function dailyChart(string $ticker, string $range = '6mo', string $interval = '1d'): array
    {
        $result = $this->yahoo->chart($ticker, $range, $interval);

        return $this->normalizeChart($result);
    }

    /**
     * Month-over-month % change grouped as [month => [year => pctChange]],
     * used by the seasonality views (detail page + dedicated seasonality
     * page both consumed a copy of this in the legacy app).
     *
     * @return array<int, array<int, float>>
     */
    public function monthlySeasonality(string $ticker, string $range = '5y'): array
    {
        $result = $this->yahoo->chart($ticker, $range, '1mo');
        $chart = $this->normalizeChart($result);

        $byMonth = [];

        foreach ($chart['timestamps'] as $i => $timestamp) {
            $open = $chart['open'][$i] ?? 0;
            $close = $chart['close'][$i] ?? 0;

            if ($open <= 0) {
                continue;
            }

            $month = (int) date('n', $timestamp);
            $year = (int) date('Y', $timestamp);
            $byMonth[$month][$year] = (($close - $open) / $open) * 100;
        }

        return $byMonth;
    }

    /**
     * Turns monthlySeasonality()'s [month => [year => pct]] map into
     * per-month averages/win-rates plus the best and worst month, shared
     * by the stock detail page and the standalone seasonality page (both
     * duplicated this aggregation in the legacy app).
     *
     * @param  array<int, array<int, float>>  $monthlyPctByYear
     * @return array{stats: array<int, array{avg: float, win_rate: float}>, best: array{month: int, value: float}, worst: array{month: int, value: float}}
     */
    public function seasonalityStats(array $monthlyPctByYear): array
    {
        $stats = [];
        $best = ['month' => 0, 'value' => -999.0];
        $worst = ['month' => 0, 'value' => 999.0];

        for ($month = 1; $month <= 12; $month++) {
            $values = $monthlyPctByYear[$month] ?? [];
            $count = count($values);
            $avg = $count > 0 ? array_sum($values) / $count : 0.0;
            $wins = count(array_filter($values, fn ($v) => $v > 0));

            $stats[$month] = [
                'avg' => $avg,
                'win_rate' => $count > 0 ? ($wins / $count) * 100 : 0.0,
            ];

            if ($avg > $best['value']) {
                $best = ['month' => $month, 'value' => $avg];
            }
            if ($avg < $worst['value']) {
                $worst = ['month' => $month, 'value' => $avg];
            }
        }

        return ['stats' => $stats, 'best' => $best, 'worst' => $worst];
    }

    /**
     * @return array{price: float, change: float, pct: float}|null
     */
    /**
     * The IHSG level and its move against the previous session's close.
     *
     * @return array{price: float, change: float, pct: float, as_of: \Illuminate\Support\Carbon|null, stale: bool}|null
     */
    public function indexQuote(string $symbol = '^JKSE'): ?array
    {
        // Five days, not one. With range=1d the response holds a single bar and
        // the only available baseline is meta.chartPreviousClose — "the close
        // before this chart began", which is not the same thing as "the
        // previous trading session" whenever the window lands across a weekend
        // or a holiday, and Yahoo shifts that window when the market is shut.
        // A week of daily bars means the previous session is in the data
        // itself, so the comparison no longer depends on interpreting a meta
        // field whose meaning changes with the request.
        //
        // Cached because this runs on every dashboard render: uncached it was a
        // blocking Yahoo call in the request path of the app's busiest page,
        // and the one most likely to trip rate limiting.
        return Cache::remember("index-quote:{$symbol}", now()->addMinutes(2), function () use ($symbol) {
            $chart = $this->normalizeChart($this->yahoo->chart($symbol, '5d', '1d'));
            $meta = $chart['meta'];

            // Yahoo pads the series with nulls for sessions it has no data for;
            // normalizeChart turns those into 0.0.
            $closes = [];
            foreach ($chart['close'] as $i => $close) {
                if ($close > 0) {
                    $closes[] = ['close' => $close, 'timestamp' => $chart['timestamps'][$i] ?? null];
                }
            }

            if (count($closes) < 2) {
                return null;
            }

            $latest = $closes[count($closes) - 1];
            $previous = $closes[count($closes) - 2];

            // The live price during a session; the last bar's close once it has
            // ended. The bar's own close is the fallback so the two can never
            // disagree about which session is being described.
            $price = (float) ($meta['regularMarketPrice'] ?? $latest['close']);
            $prevClose = (float) $previous['close'];

            if ($price <= 0 || $prevClose <= 0) {
                return null;
            }

            $asOf = isset($meta['regularMarketTime'])
                ? Carbon::createFromTimestamp((int) $meta['regularMarketTime'], config('app.timezone'))
                : ($latest['timestamp'] ? Carbon::createFromTimestamp((int) $latest['timestamp'], config('app.timezone')) : null);

            return [
                'price' => $price,
                'change' => $price - $prevClose,
                'pct' => (($price - $prevClose) / $prevClose) * 100,
                'as_of' => $asOf,
                // Outside a session Yahoo keeps serving the last one, so the
                // figure is real but it is not today's. Saying which day it
                // belongs to is the difference between "the market is up" and
                // "the market was up on Friday" — the header presented the
                // second as the first.
                'stale' => $asOf !== null && ! $asOf->isSameDay(Carbon::now(config('app.timezone'))),
            ];
        });
    }

    public function livePrice(string $ticker): float
    {
        $result = $this->yahoo->chart($ticker, '1d', '1d');

        return (float) ($result['meta']['regularMarketPrice'] ?? 0);
    }

    /**
     * @return array{roe: float, per: float, pbv: float, der: float, market_cap: float}
     */
    public function fundamentals(string $ticker): array
    {
        $summary = $this->yahoo->quoteSummary($ticker, ['financialData', 'defaultKeyStatistics']);
        $financialData = $summary['financialData'] ?? [];
        $keyStats = $summary['defaultKeyStatistics'] ?? [];

        $roe = isset($financialData['returnOnEquity']['raw'])
            ? $financialData['returnOnEquity']['raw'] * 100
            : 0.0;

        $per = $keyStats['forwardPE']['raw'] ?? $keyStats['trailingPE']['raw'] ?? 0.0;
        $pbv = $keyStats['priceToBook']['raw'] ?? 0.0;

        $derRaw = $financialData['debtToEquity']['raw'] ?? 0.0;
        // Yahoo sometimes reports debt/equity as a percentage (e.g. 145.2
        // instead of 1.452) — normalize back to a ratio, matching the
        // legacy update_fundamentals.php heuristic.
        $der = $derRaw > 10 ? $derRaw / 100 : $derRaw;

        $marketCap = $keyStats['enterpriseValue']['raw'] ?? $financialData['marketCap']['raw'] ?? 0.0;

        return [
            'roe' => (float) $roe,
            'per' => (float) $per,
            'pbv' => (float) $pbv,
            'der' => (float) $der,
            'market_cap' => (float) $marketCap,
        ];
    }

    /**
     * Bulk realtime quotes for the whole IDX universe via TradingView,
     * normalized to one row per ticker. Includes company_name/sector so
     * callers can auto-register tickers that don't exist in stock_refs
     * yet — the TradingView scan effectively *is* a live, self-updating
     * IDX ticker list, so nothing needs to be imported by hand.
     *
     * Carries close, volume, vwap and previous_close. The last of those has to
     * come from here rather than from the nightly Yahoo refresh: it is the
     * baseline for the close in the same row, and a baseline lagging its price
     * by a session is what made the daily change a two-day move.
     *
     * prev_close is null when TradingView omits or zeroes it, and callers are
     * expected to leave the stored value alone in that case rather than write
     * a zero — a missing baseline must not become "up by the entire price".
     *
     * vol_avg_20 is still not sourced here: the only window this endpoint
     * offers is 30 days and the app's field is a 20-day average.
     *
     * @return array<int, array{ticker: string, company_name: ?string, sector: ?string, close: float, volume: int, vwap: float, value_transaction: float, prev_close: ?float}>
     */
    public function realtimeScan(): array
    {
        $rows = $this->tradingView->scanIndonesiaExchange();
        $normalized = [];

        foreach ($rows as $row) {
            $d = $row['d'] ?? [];

            if (count($d) < 6) {
                continue;
            }

            [$symbol, $description, $sector, $close, $volume, $vwap] = $d;
            // Index 6 rather than destructured, so a response that predates the
            // previous_close column still yields usable rows.
            $prevClose = $d[6] ?? null;

            $close = (float) $close;
            $volume = (int) $volume;

            $normalized[] = [
                'ticker' => $this->yahoo->normalizeTicker((string) $symbol),
                'company_name' => is_string($description) && $description !== '' ? $description : null,
                'sector' => is_string($sector) && $sector !== '' ? $sector : null,
                'close' => $close,
                'volume' => $volume,
                'vwap' => $vwap !== null ? (float) $vwap : $close,
                'value_transaction' => $close * $volume,
                'prev_close' => is_numeric($prevClose) && (float) $prevClose > 0 ? (float) $prevClose : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return array{timestamps: int[], open: float[], high: float[], low: float[], close: float[], volume: int[], meta: array}
     */
    private function normalizeChart(array $result): array
    {
        $timestamps = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];

        return [
            'timestamps' => $timestamps,
            'open' => array_map(fn ($v) => (float) ($v ?? 0), $quote['open'] ?? []),
            'high' => array_map(fn ($v) => (float) ($v ?? 0), $quote['high'] ?? []),
            'low' => array_map(fn ($v) => (float) ($v ?? 0), $quote['low'] ?? []),
            'close' => array_map(fn ($v) => (float) ($v ?? 0), $quote['close'] ?? []),
            'volume' => array_map(fn ($v) => (int) ($v ?? 0), $quote['volume'] ?? []),
            'meta' => $result['meta'] ?? [],
        ];
    }
}
