<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPriceAlert extends Model
{
    protected $fillable = [
        'user_id',
        'ticker',
        'direction',
        'target_price',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'target_price' => 'decimal:2',
            'triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stockRef(): BelongsTo
    {
        return $this->belongsTo(StockRef::class, 'ticker', 'ticker');
    }

    public function isActive(): bool
    {
        return $this->triggered_at === null;
    }

    public function isCrossedBy(float $currentPrice): bool
    {
        return $this->direction === 'above'
            ? $currentPrice >= (float) $this->target_price
            : $currentPrice <= (float) $this->target_price;
    }
}
