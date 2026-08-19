<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPriceHistory extends Model
{
    protected $table = 'stock_price_histories';

    protected $fillable = [
        'ticker',
        'date',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'open' => 'decimal:2',
            'high' => 'decimal:2',
            'low' => 'decimal:2',
            'close' => 'decimal:2',
            'volume' => 'integer',
        ];
    }

    public function stockRef(): BelongsTo
    {
        return $this->belongsTo(StockRef::class, 'ticker', 'ticker');
    }

    /**
     * The most recent completed session for every ticker, keyed by ticker.
     *
     * This is the app's own answer to "what did it close at last time", and
     * it is the one figure in the system that carries its own date. The daily
     * change needs exactly that: a baseline that can be checked rather than
     * trusted.
     *
     * "Completed" means strictly before today, which is what makes it usable
     * during a session and after it alike. The backfill writes today's bar at
     * 16:30, so from then until midnight the newest row *is* today — and
     * comparing today's close against itself would report every emiten as
     * unchanged.
     *
     * One query for the whole exchange rather than one per ticker: the
     * realtime refresh runs over ~900 rows every few minutes, where a lookup
     * each would be the most expensive thing it does.
     *
     * @return array<string, array{close: float, date: string}>
     */
    public static function lastCompletedSessions(?string $before = null): array
    {
        $before ??= now()->toDateString();

        $latest = static::query()
            ->selectRaw('ticker, MAX(date) as date')
            ->whereDate('date', '<', $before)
            ->groupBy('ticker');

        return static::query()
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('stock_price_histories.ticker', '=', 'latest.ticker')
                    ->on('stock_price_histories.date', '=', 'latest.date');
            })
            ->get(['stock_price_histories.ticker', 'stock_price_histories.close', 'stock_price_histories.date'])
            ->mapWithKeys(fn (self $bar) => [
                $bar->ticker => ['close' => (float) $bar->close, 'date' => $bar->date->toDateString()],
            ])
            ->all();
    }
}
