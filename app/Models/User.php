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
    ];

    protected $hidden = [
        'password',
        'remember_token',
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
        ];
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
