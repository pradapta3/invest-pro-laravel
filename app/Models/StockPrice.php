<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPrice extends Model
{
    protected $table = 'stock_prices';

    protected $primaryKey = 'ticker';

    protected $keyType = 'string';

    public $incrementing = false;

    const UPDATED_AT = 'updated_at';

    const CREATED_AT = null;

    protected $fillable = [
        'ticker',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'prev_close',
        'volume',
        'ma20',
        'rsi_14',
        'stoch_k',
        'macd_hist',
        'is_breakout',
        'vol_avg_20',
        'value_transaction',
        'vwap',
        'buying_pressure',
        'selling_pressure',
        'history_json',
        'sentiment_score',
        'last_news_title',
    ];

    protected function casts(): array
    {
        return [
            'open_price' => 'decimal:2',
            'high_price' => 'decimal:2',
            'low_price' => 'decimal:2',
            'close_price' => 'decimal:2',
            'prev_close' => 'decimal:2',
            'volume' => 'integer',
            'ma20' => 'decimal:2',
            'rsi_14' => 'decimal:2',
            'stoch_k' => 'decimal:2',
            'macd_hist' => 'decimal:5',
            'is_breakout' => 'boolean',
            'vol_avg_20' => 'integer',
            'value_transaction' => 'decimal:2',
            'vwap' => 'decimal:2',
            'buying_pressure' => 'decimal:2',
            'selling_pressure' => 'decimal:2',
            'history_json' => 'array',
            'sentiment_score' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function stockRef(): BelongsTo
    {
        return $this->belongsTo(StockRef::class, 'ticker', 'ticker');
    }

    /**
     * Rows with a sane, currently-traded price. Every screener strategy
     * starts from this baseline, matching the legacy `close_price > 50 AND
     * volume > 0` filter repeated across quant_scan.php, index.php, etc.
     */
    public function scopeTradable(Builder $query, float $minPrice = 50): Builder
    {
        return $query->where('close_price', '>', $minPrice)->where('volume', '>', 0);
    }

    /**
     * Rows with at least the given Rupiah transaction value today —
     * used to filter out illiquid/thinly traded tickers.
     */
    public function scopeMinTransactionValue(Builder $query, float $minValue): Builder
    {
        return $query->where('value_transaction', '>=', $minValue);
    }

    public function volumeSpikeRatio(): float
    {
        $avg = (float) $this->vol_avg_20;

        return $avg > 0 ? (float) $this->volume / $avg : 0.0;
    }

    /**
     * Whether this row's derived indicators — ma20, rsi_14, stoch_k,
     * macd_hist — have actually been computed.
     *
     * Those columns are NOT NULL DEFAULT 0, so an untouched row is
     * indistinguishable from a computed one by value alone, and 0 is a real
     * reading for three of the four: %K = 0 is the *most* oversold a stock
     * can be, and macd_hist crosses zero constantly. Testing each field for
     * `> 0` therefore throws away genuine extremes.
     *
     * ma20 answers it for all of them instead. UpdateMarketData skips a
     * ticker outright when there is not enough history, and otherwise writes
     * every one of these fields together from a 20-close mean that cannot be
     * zero for a listed stock. So ma20 > 0 means the whole set is real.
     */
    public function hasIndicators(): bool
    {
        return (float) $this->ma20 > 0;
    }

    /**
     * Whether the close sits above MA20, or null when MA20 was never
     * computed. Same reason as moneyFlow() below: written out by hand,
     * `close > 0` is true against a missing MA20, so the dashboard called
     * every unprocessed row BULLISH.
     */
    public function isAboveMa20(): ?bool
    {
        if (! $this->hasIndicators()) {
            return null;
        }

        return (float) $this->close_price > (float) $this->ma20;
    }

    /**
     * Where the day's money sat relative to VWAP: 'AKUM' when the close is
     * above it, 'DIST' below, and null when that cannot be told.
     *
     * Null is the point of this method. VWAP is only ever written by
     * idx:update-realtime-quotes, so until that command's first successful
     * run it is 0 for every emiten. Five call sites each wrote this
     * comparison out by hand and three compared against that 0 unguarded, so
     * `close > 0` came out true and the whole exchange read as accumulating.
     * The two that did guard fell the other way and labelled it all
     * distributing — the same emiten reading AKUM in Telegram and DIST on the
     * dashboard at the same moment.
     *
     * close == vwap is unknown rather than DIST. MarketDataService stores the
     * close as the VWAP when TradingView returns none, so equality is how a
     * ticker with no VWAP data arrives; and where it is genuine — an illiquid
     * name whose only trade of the day set both — it is neither accumulation
     * nor distribution. Both readings agree that it is not a red flag.
     *
     * Callers are expected to render null as "-" rather than picking a side.
     */
    public function moneyFlow(): ?string
    {
        $vwap = (float) $this->vwap;
        $close = (float) $this->close_price;

        if ($vwap <= 0 || $close === $vwap) {
            return null;
        }

        return $close > $vwap ? 'AKUM' : 'DIST';
    }

    /**
     * Trailing daily closes decoded from history_json, oldest first.
     *
     * @return array<int, float>
     */
    public function closeHistory(): array
    {
        $raw = $this->history_json ?? [];
        $closes = [];

        foreach ($raw as $point) {
            if (is_numeric($point)) {
                $closes[] = (float) $point;
            } elseif (is_array($point)) {
                $closes[] = (float) ($point['close'] ?? $point['Close'] ?? $point['c'] ?? 0);
            }
        }

        return array_values($closes);
    }
}
