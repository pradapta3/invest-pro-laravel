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
     *  ordered as [ticker, description, sector, close, volume, vwap, average_volume_30d, change].
     *  description/sector let callers auto-register tickers stock_refs has
     *  never seen before, instead of requiring a manual CSV/LQ45 import.
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
            'columns' => ['name', 'description', 'sector', 'close', 'volume', 'VWAP', 'average_volume_30d_calc', 'change'],
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
