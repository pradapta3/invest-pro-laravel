<?php

namespace App\Services\MarketData;

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
    public function indexQuote(string $symbol = '^JKSE'): ?array
    {
        $result = $this->yahoo->chart($symbol, '1d', '1d');
        $meta = $result['meta'] ?? [];

        if (! isset($meta['regularMarketPrice'], $meta['chartPreviousClose']) || (float) $meta['chartPreviousClose'] === 0.0) {
            return null;
        }

        $price = (float) $meta['regularMarketPrice'];
        $prevClose = (float) $meta['chartPreviousClose'];

        return [
            'price' => $price,
            'change' => $price - $prevClose,
            'pct' => (($price - $prevClose) / $prevClose) * 100,
        ];
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
     * @return array<int, array{ticker: string, company_name: ?string, sector: ?string, close: float, volume: int, vwap: float, avg_volume: int, prev_close: float, value_transaction: float}>
     */
    public function realtimeScan(): array
    {
        $rows = $this->tradingView->scanIndonesiaExchange();
        $normalized = [];

        foreach ($rows as $row) {
            $d = $row['d'] ?? [];

            if (count($d) < 8) {
                continue;
            }

            [$symbol, $description, $sector, $close, $volume, $vwap, $avgVolume, $changeAbs] = $d;

            $close = (float) $close;
            $volume = (int) $volume;

            $normalized[] = [
                'ticker' => $this->yahoo->normalizeTicker((string) $symbol),
                'company_name' => is_string($description) && $description !== '' ? $description : null,
                'sector' => is_string($sector) && $sector !== '' ? $sector : null,
                'close' => $close,
                'volume' => $volume,
                'vwap' => $vwap !== null ? (float) $vwap : $close,
                'avg_volume' => (int) $avgVolume,
                // Close = Prev + Change  =>  Prev = Close - Change.
                'prev_close' => $close - (float) $changeAbs,
                'value_transaction' => $close * $volume,
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
