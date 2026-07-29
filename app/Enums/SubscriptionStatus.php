<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    // New signups land here — no payment gateway is wired up, so an admin
    // activates the subscription by hand from the admin dashboard.
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Aktivasi',
            self::Active => 'Aktif',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
