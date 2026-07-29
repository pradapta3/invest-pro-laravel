<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Placeholder pricing tiers shown on the registration page — adjust
     * freely from Admin > Paket Langganan, nothing else in the app
     * hardcodes these names/prices.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price_per_month' => 99000,
                'description' => 'Untuk investor pemula yang ingin mulai analisa saham IDX.',
                'features' => ['Dashboard & skor AI', 'Semua screener', 'Portfolio simulasi'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_per_month' => 249000,
                'description' => 'Untuk trader aktif yang butuh sinyal dan backtest.',
                'features' => ['Semua fitur Basic', 'Titan Radar & Ghost Pattern', 'Backtest engine', 'Broadcast Telegram'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'price_per_month' => 499000,
                'description' => 'Akses penuh + prioritas dukungan.',
                'features' => ['Semua fitur Pro', 'Analisa AI Gemini tanpa batas', 'Dukungan prioritas'],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
