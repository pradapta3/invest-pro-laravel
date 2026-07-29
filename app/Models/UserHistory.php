<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHistory extends Model
{
    protected $table = 'user_histories';

    const UPDATED_AT = null;

    const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'ticker',
        'type',
        'price',
        'lots',
        'value',
        'profit_loss',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'price' => 'decimal:2',
            'lots' => 'integer',
            'value' => 'decimal:2',
            'profit_loss' => 'decimal:2',
            'date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
