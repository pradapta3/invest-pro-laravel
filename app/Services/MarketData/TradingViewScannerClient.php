<?php

namespace App\Services\MarketData;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bulk realtime quote scraper against TradingView's undocumented scanner
 * endpoint, used to refresh the whole IDX universe in one request instead
 * of one Yahoo call per ticker (see legacy update_realtime.php).
 */
class TradingViewScannerClient
{
    /**
     * @return array<int, array{d: array<int, mixed>}> raw rows, each `d`
     *  ordered as [ticker, description, sector, close, volume, vwap].
     *  description/sector let callers auto-register tickers stock_refs has
     *  never seen before, instead of requiring a manual CSV/LQ45 import.
     *
     *  Deliberately does NOT request average_volume_*_calc or change here
     *  anymore — average_volume_30d_calc was previously being written
     *  straight into the app's "20-day" volume average field (wrong
     *  window), and `change` from this endpoint is a *percentage*, not an
     *  absolute Rupiah delta, which was being subtracted from `close` as
     *  if it were one. Both vol_avg_20 and prev_close are now sourced
     *  once a day from Yahoo's own OHLCV history in UpdateMarketData
     *  instead, which is unambiguous.
     */
    public function scanIndonesiaExchange(): array
    {
        $payload = [
            'filter' => [
                ['left' => 'exchange', 'operation' => 'equal', 'right' => 'IDX'],
                ['left' => 'active_symbol', 'operation' => 'equal', 'right' => true],
            ],
            'options' => ['lang' => 'id'],
            'symbols' => ['query' => ['types' => []], 'tickers' => []],
            'columns' => ['name', 'description', 'sector', 'close', 'volume', 'VWAP'],
            'sort' => ['sortBy' => 'volume', 'sortOrder' => 'desc'],
            // IDX lists ~850-900 active symbols; 1200 leaves headroom for growth.
            'range' => [0, 1200],
        ];

        $response = Http::withHeaders([
            'User-Agent' => config('services.tradingview.user_agent'),
            'Authority' => 'scanner.tradingview.com',
        ])
            ->timeout(config('services.tradingview.timeout'))
            ->withOptions(['verify' => config('services.tradingview.verify_ssl')])
            ->post(config('services.tradingview.scanner_url'), $payload);

        if (! $response->successful()) {
            Log::channel('market_data')->warning('TradingView scan failed', ['status' => $response->status()]);

            return [];
        }

        return $response->json('data') ?? [];
    }
}
