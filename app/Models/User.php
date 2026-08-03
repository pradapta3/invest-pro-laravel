<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'telegram_chat_id',
        'telegram_link_code',
        'telegram_link_code_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'telegram_link_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Laravel hashes the value automatically on assignment/save —
            // never call Hash::make() yourself when using this cast, that
            // would hash it twice and lock the account out.
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'telegram_link_code_expires_at' => 'datetime',
        ];
    }

    public function hasLinkedTelegram(): bool
    {
        return ! empty($this->telegram_chat_id);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(UserWallet::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(UserPortfolio::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(UserHistory::class);
    }

    public function priceAlerts(): HasMany
    {
        return $this->hasMany(UserPriceAlert::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return BelongsToMany<StockRef>
     */
    public function watchlist(): BelongsToMany
    {
        return $this->belongsToMany(StockRef::class, 'user_watchlists', 'user_id', 'ticker', 'id', 'ticker')
            ->withTimestamps();
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->with('plan')
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->currentSubscription()?->isCurrentlyActive() ?? false;
    }
}
