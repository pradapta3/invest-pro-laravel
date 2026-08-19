<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        // Keyed by ticker and carrying the stored baseline, so a row whose
        // scan brought no previous_close can keep the one it already had
        // without a query per ticker to look it up.
        $storedPrevClose = StockPrice::query()->pluck('prev_close', 'ticker');

        $newRefs = [];
        $priceUpdates = [];
        $missingPrevClose = 0;
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
            if (! $storedPrevClose->has($ticker)) {
                continue;
            }

            // prev_close moves with close_price or not at all. Left to the
            // nightly refresh it lagged a session behind: after Monday's
            // 16:15 run, close_price was Monday's close and prev_close was
            // Friday's — then Tuesday's first realtime scan advanced
            // close_price to Tuesday and left prev_close on Friday, so every
            // "today's change" in the app was a two-day move for the whole
            // session. A stock down on Tuesday but above Friday's close read
            // green.
            //
            // Rows where TradingView gave no usable baseline keep the one
            // already stored rather than taking a zero, which would render as
            // a gain of the entire share price.
            $update = [
                'ticker' => $ticker,
                'close_price' => $row['close'],
                'volume' => $row['volume'],
                'vwap' => $row['vwap'],
                'value_transaction' => $row['value_transaction'],
                'prev_close' => $row['prev_close'] ?? (float) $storedPrevClose[$ticker],
            ];

            if ($row['prev_close'] === null) {
                $missingPrevClose++;
            }

            $priceUpdates[$ticker] = $update;

            // vol_avg_20 is still left alone — idx:update-market-data owns it.
        }

        // insertOrIgnore, not insert: the admin "Update Realtime" button queues
        // this command outside the scheduler's withoutOverlapping lock, so two
        // runs can overlap. Both snapshot $knownRefs before either writes, so a
        // plain insert() would hit a duplicate primary key and abort the run
        // before a single price was updated. Ignoring is also what we want
        // semantically — discovering a ticker must not overwrite a company name
        // or sector someone corrected by hand, which is why this is not upsert.
        foreach (array_chunk($newRefs, 500) as $chunk) {
            StockRef::query()->insertOrIgnore($chunk);
        }

        foreach (array_chunk($priceUpdates, 500) as $chunk) {
            StockPrice::query()->upsert(
                $chunk,
                ['ticker'],
                ['close_price', 'volume', 'vwap', 'value_transaction', 'prev_close'],
            );
        }

        $discovered = count($newRefs);
        $updated = count($priceUpdates);

        $this->info("Updated {$updated} of ".count($rows).' scanned tickers.');
        if ($discovered > 0) {
            $this->info("Discovered {$discovered} new ticker(s) — run idx:update-market-data to populate their OHLC/indicators.");
        }

        // Loud rather than silent. Those rows kept yesterday's baseline against
        // today's price, which is the two-day-move bug this command exists to
        // avoid; if it is the whole exchange, TradingView has stopped serving
        // the column and the daily change is wrong everywhere until it returns.
        if ($missingPrevClose > 0) {
            $message = "{$missingPrevClose} ticker(s) came back without a previous close; their stored baseline was kept.";

            $this->warn($message);
            Log::channel('market_data')->warning('Realtime scan missing previous_close', [
                'tickers_affected' => $missingPrevClose,
                'tickers_updated' => $updated,
            ]);

            if ($missingPrevClose === $updated) {
                $this->error('None of them had one — check that the scanner still returns the previous_close column.');
            }
        }

        return self::SUCCESS;
    }
}
