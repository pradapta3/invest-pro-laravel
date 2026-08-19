<?php

namespace App\Services\MarketData;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bulk realtime quote scraper against TradingView's undocumented scanner
 * endpoint, used to refresh the whole IDX universe in one request instead
 * of one Yahoo call per ticker (see legacy update_realtime.php).
 */
class TradingViewScannerClient
{
    /**
     * @return array<int, array{d: array<int, mixed>}> raw rows, each `d`
     *  ordered as [ticker, description, sector, close, volume, vwap,
     *  previous_close]. description/sector let callers auto-register tickers
     *  stock_refs has never seen before, instead of requiring a manual
     *  CSV/LQ45 import.
     *
     *  previous_close is requested alongside close because the two have to
     *  describe the same instant. It was previously left to UpdateMarketData's
     *  nightly Yahoo refresh, which meant that during a session close_price
     *  had advanced to today while prev_close still held the close from *two*
     *  sessions back — the daily change on every page was a two-day move, and
     *  a stock down today but above the day-before-yesterday's close showed
     *  green. Taking both from one response makes that impossible.
     *
     *  This is not the field that was removed before. `change` from this
     *  endpoint is a *percentage* and was being subtracted from `close` as
     *  though it were an absolute Rupiah delta; previous_close is an absolute
     *  price and needs no arithmetic. average_volume_*_calc is still not
     *  requested — the only window offered is 30d and the app's field is a
     *  20-day average, so vol_avg_20 remains UpdateMarketData's to own.
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
            'columns' => ['name', 'description', 'sector', 'close', 'volume', 'VWAP', 'previous_close'],
            'sort' => ['sortBy' => 'volume', 'sortOrder' => 'desc'],
            // IDX lists ~850-900 active symbols; 1200 leaves headroom for growth.
            'range' => [0, 1200],
        ];

        // An error *status* was already handled below; a transport failure —
        // DNS, TLS, a proxy refusing CONNECT, or the timeout expiring — throws
        // instead, and used to escape all the way out of the scheduled
        // command, which then died having updated nothing. Every caller
        // already treats an empty result as "no data", so upstream being
        // unreachable degrades to that rather than to a stack trace.
        try {
            $response = Http::withHeaders([
                'User-Agent' => config('services.tradingview.user_agent'),
                'Authority' => 'scanner.tradingview.com',
            ])
                ->timeout(config('services.tradingview.timeout'))
                ->withOptions(['verify' => config('services.tradingview.verify_ssl')])
                ->post(config('services.tradingview.scanner_url'), $payload);
        } catch (Throwable $e) {
            Log::channel('market_data')->warning('TradingView scan could not be made', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::channel('market_data')->warning('TradingView scan failed', ['status' => $response->status()]);

            return [];
        }

        return $response->json('data') ?? [];
    }
}
