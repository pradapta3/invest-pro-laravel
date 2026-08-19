<?php

namespace App\Models;

use App\Support\IdxPrice;
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
        'prev_close_date',
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
            'prev_close_date' => 'date',
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
     * What today's move is measured against: the previous close, which is what
     * IDX, every broker screen and every quote site mean by "change".
     *
     * Returns null rather than guessing. It used to fall back to open_price,
     * on the reasoning that the intraday move is at least a real number for
     * today — but it is neither. open_price is written only by the nightly
     * end-of-day refresh, so during a session it holds the *previous* day's
     * open; and measuring from the open is exactly the mistake this method
     * exists to stop, because it discards the overnight gap. An emiten that
     * gaps down and recovers a little is deeply red for the day and shows
     * close > open, so the fallback painted green precisely on the rows where
     * being wrong mattered most. A dash is worse than a number only when the
     * number is right.
     */
    public function dailyChangeBase(): ?float
    {
        $prevClose = (float) $this->prev_close;

        return $prevClose > 0 && ! $this->baselineIsStale() ? $prevClose : null;
    }

    /**
     * Whether the stored baseline belongs to a session too far back to call
     * its difference from today's price "today's change".
     *
     * The baseline used to be a bare number with no provenance, which is the
     * root of this bug in all three of its appearances: one left over from an
     * earlier session looks exactly like a correct one, so a two- or
     * three-day move gets presented as the day's. prev_close_date fixes that
     * by recording which session the figure closed.
     *
     * A null date means the row predates that column, and is treated as
     * usable — the alternative is blanking every board on the first deploy
     * until a scheduled refresh lands. The refreshes fill it in within
     * minutes, after which this check has teeth.
     */
    public function baselineIsStale(): bool
    {
        if ($this->prev_close_date === null) {
            return false;
        }

        // Against the price's own timestamp, not against now: after Friday's
        // close a Thursday baseline is still the correct one for the Friday
        // price sitting beside it, and stays correct all weekend.
        $priceAt = $this->updated_at ?? now();

        return $this->prev_close_date->diffInDays($priceAt) > config('screener.baseline_max_age_days');
    }

    /**
     * Whether the day's move is one the exchange could actually have allowed.
     *
     * IDX auto-rejects orders beyond a band around the previous close, so a
     * change well past that band is not a violent day — it is a baseline and
     * a price that are not on the same basis. A stock split is the usual
     * cause: the history feed adjusts for it and the live quote does not, so
     * a Rp20,000 price gets measured against a Rp2,000 baseline and the app
     * reports a 900% gain with total confidence.
     */
    public function dailyChangeIsPlausible(): bool
    {
        $base = (float) $this->prev_close;
        $close = (float) $this->close_price;

        if ($base <= 0 || $close <= 0) {
            return false;
        }

        $movePct = abs(($close - $base) / $base) * 100;
        $limit = IdxPrice::autoRejectionPct($base) * config('screener.change_tolerance');

        return $movePct <= $limit;
    }

    /**
     * Today's move in rupiah, or null when there is nothing to compare against.
     *
     * Three surfaces each worked this out for themselves and two got it wrong:
     * the detail page and the ticker tape used close - open, which measures
     * only the intraday drift and throws away the overnight gap. On a falling
     * market that is the difference between red and green — a stock that gaps
     * down at the open and recovers a little through the session is down for
     * the day but shows `close > open`. The heatmap alone compared against the
     * previous close, so the same emiten could appear green on one page and
     * red on another at the same moment.
     */
    public function dailyChange(): ?float
    {
        $base = $this->dailyChangeBase();

        if ($base === null || ! $this->dailyChangeIsPlausible()) {
            return null;
        }

        return (float) $this->close_price - $base;
    }

    /**
     * Why there is no daily change to show, in words, or null when there is.
     *
     * A dash with no explanation is its own kind of unhelpful — the reader
     * cannot tell a stock nobody has quoted yet from one whose baseline is
     * three sessions old, and neither can whoever has to fix it. Each of
     * these names the command that repairs it.
     */
    public function dailyChangeIssue(): ?string
    {
        if ((float) $this->prev_close <= 0) {
            return 'Belum ada harga penutupan sebelumnya untuk pembanding. Jalankan idx:update-realtime-quotes, atau idx:backfill-price-history bila emiten ini belum punya riwayat.';
        }

        if ($this->baselineIsStale()) {
            return sprintf(
                'Pembanding masih dari sesi %s — terlalu lama untuk disebut perubahan hari ini. Periksa scheduler dan jalankan idx:quote-check %s.',
                $this->prev_close_date->translatedFormat('d M Y'),
                str_replace('.JK', '', $this->ticker),
            );
        }

        if (! $this->dailyChangeIsPlausible()) {
            return sprintf(
                'Selisih terhadap penutupan sebelumnya (%s) melebihi batas auto rejection bursa — hampir pasti harga dan pembandingnya beda basis, misalnya stock split yang belum disesuaikan. Jalankan idx:quote-check %s.',
                number_format((float) $this->prev_close),
                str_replace('.JK', '', $this->ticker),
            );
        }

        return null;
    }

    public function dailyChangePct(): ?float
    {
        $base = $this->dailyChangeBase();
        $change = $this->dailyChange();

        return $base === null || $change === null ? null : ($change / $base) * 100;
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
