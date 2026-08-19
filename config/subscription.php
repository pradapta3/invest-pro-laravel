<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan features
    |--------------------------------------------------------------------------
    |
    | The canonical list an admin ticks per plan (admin/plans/edit), stored as
    | keys in subscription_plans.features and enforced by the `plan` middleware
    | (EnsurePlanFeature) on the routes below each one.
    |
    | Adding a key here adds a checkbox to the plan form; it only starts gating
    | anything once a route carries the matching plan:<key> middleware.
    |
    */

    'features' => [
        'scanner' => 'Screener Quant & Titan Sniper',
        'backtest' => 'Backtest engine',
        'pattern' => 'Seasonality & Ghost Pattern',
        'heatmap' => 'Market Map (heatmap)',
        'tools' => 'Kalkulator & Tools',
        'telegram' => 'Notifikasi Telegram',
        'ai' => 'Analisa AI Gemini',
    ],

];
