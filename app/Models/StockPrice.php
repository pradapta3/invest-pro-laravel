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
