<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use Illuminate\Console\Command;

/**
 * Bulk realtime close/volume/VWAP refresh via the TradingView scanner,
 * replacing update_realtime.php.
 *
 * Also auto-registers any ticker the scan returns that isn't in
 * stock_refs yet (using the company name/sector TradingView already
 * supplies) — the legacy app required a manual CSV upload or the
 * hardcoded LQ45 list to grow the ticker universe; this command now
 * discovers new listings on its own every time it runs. A newly
 * discovered ticker only gets a stock_refs row here — its stock_prices
 * row (OHLC, MA20, RSI, etc.) is populated by the next
 * idx:update-market-data run, which already loops every stock_refs
 * ticker.
 */
class UpdateRealtimeQuotes extends Command
{
    protected $signature = 'idx:update-realtime-quotes';

    protected $description = 'Bulk-refresh realtime close/volume/VWAP for the whole IDX universe via the TradingView scanner, auto-registering any new tickers found';

    public function __construct(private readonly MarketDataService $marketData)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rows = $this->marketData->realtimeScan();

        if (empty($rows)) {
            $this->error('TradingView scan returned no data.');

            return self::FAILURE;
        }

        $updated = 0;
        $discovered = 0;

        foreach ($rows as $row) {
            $ref = StockRef::query()->find($row['ticker']);

            if ($ref === null) {
                StockRef::query()->create([
                    'ticker' => $row['ticker'],
                    'nama_perusahaan' => $row['company_name'] ?? str_replace('.JK', '', $row['ticker']),
                    'sector' => $row['sector'] ?? 'Others',
                ]);
                $discovered++;

                continue;
            }

            $updated += StockPrice::query()->where('ticker', $row['ticker'])->update([
                'close_price' => $row['close'],
                'volume' => $row['volume'],
                'vwap' => $row['vwap'],
                'value_transaction' => $row['value_transaction'],
                // vol_avg_20 and prev_close are intentionally left alone here —
                // idx:update-market-data (daily, from Yahoo's own OHLCV history)
                // is the only accurate source for both. See MarketDataService::
                // realtimeScan() docblock for why this used to be wrong.
            ]);
        }

        $this->info("Updated {$updated} of ".count($rows).' scanned tickers.');
        if ($discovered > 0) {
            $this->info("Discovered {$discovered} new ticker(s) — run idx:update-market-data to populate their OHLC/indicators.");
        }

        return self::SUCCESS;
    }
}
