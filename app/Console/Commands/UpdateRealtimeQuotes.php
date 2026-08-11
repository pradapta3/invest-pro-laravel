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

        // Two lookup tables up front instead of queries inside the loop. The
        // scan returns the whole exchange, so find()-per-row plus
        // update()-per-row cost roughly two statements per ticker — around
        // 1800 round trips a run once every listing is registered. That was
        // tolerable every fifteen minutes and is not at the five-minute
        // default, let alone the every-minute setting IDX_REALTIME_CRON allows.
        $knownRefs = StockRef::query()->pluck('ticker')->flip();
        $pricedTickers = StockPrice::query()->pluck('ticker')->flip();

        $newRefs = [];
        $priceUpdates = [];
        $now = now();

        foreach ($rows as $row) {
            $ticker = $row['ticker'];

            if (! $knownRefs->has($ticker)) {
                // Keyed by ticker so a scan that repeats one does not produce a
                // duplicate-key error on the bulk insert below.
                $newRefs[$ticker] = [
                    'ticker' => $ticker,
                    'nama_perusahaan' => $row['company_name'] ?? str_replace('.JK', '', $ticker),
                    'sector' => $row['sector'] ?? 'Others',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                continue;
            }

            // Only tickers that already have a price row. upsert() would
            // otherwise insert one with every indicator defaulted to zero, and
            // the screener would list it with a meaningless MA20 and RSI —
            // creating those rows belongs to idx:update-market-data, which has
            // the OHLCV history to compute them from.
            if (! $pricedTickers->has($ticker)) {
                continue;
            }

            $priceUpdates[$ticker] = [
                'ticker' => $ticker,
                'close_price' => $row['close'],
                'volume' => $row['volume'],
                'vwap' => $row['vwap'],
                'value_transaction' => $row['value_transaction'],
                // vol_avg_20 and prev_close are intentionally left alone here —
                // idx:update-market-data (daily, from Yahoo's own OHLCV history)
                // is the only accurate source for both. See MarketDataService::
                // realtimeScan() docblock for why this used to be wrong.
            ];
        }

        // insert() rather than upsert() for the refs: discovering a ticker must
        // not overwrite a company name or sector someone corrected by hand.
        foreach (array_chunk($newRefs, 500) as $chunk) {
            StockRef::query()->insert($chunk);
        }

        foreach (array_chunk($priceUpdates, 500) as $chunk) {
            StockPrice::query()->upsert(
                $chunk,
                ['ticker'],
                ['close_price', 'volume', 'vwap', 'value_transaction'],
            );
        }

        $discovered = count($newRefs);
        $updated = count($priceUpdates);

        $this->info("Updated {$updated} of ".count($rows).' scanned tickers.');
        if ($discovered > 0) {
            $this->info("Discovered {$discovered} new ticker(s) — run idx:update-market-data to populate their OHLC/indicators.");
        }

        return self::SUCCESS;
    }
}
