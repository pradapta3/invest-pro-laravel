<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPortfolio extends Model
{
    protected $table = 'user_portfolios';

    protected $fillable = [
        'user_id',
        'ticker',
        'avg_price',
        'lots',
        'target_price',
        'stop_loss',
    ];

    protected function casts(): array
    {
        return [
            'avg_price' => 'decimal:2',
            'lots' => 'integer',
            'target_price' => 'decimal:2',
            'stop_loss' => 'decimal:2',
        ];
    }

    public function stockRef(): BelongsTo
    {
        return $this->belongsTo(StockRef::class, 'ticker', 'ticker');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * IDX lot size is 100 shares/lot; see config('trading.lot_size').
     */
    public function shareCount(): int
    {
        return $this->lots * config('trading.lot_size');
    }

    public function costBasis(): float
    {
        return (float) $this->avg_price * $this->shareCount();
    }
}
