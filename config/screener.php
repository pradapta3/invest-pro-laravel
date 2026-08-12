<?php

/*
|--------------------------------------------------------------------------
| Screener Thresholds
|--------------------------------------------------------------------------
|
| Every magic number the legacy scripts scattered across index.php,
| telegram_bot.php, bot_loop.php, quant_scan.php and titan_scan.php lives
| here now. Several strategies existed as near-duplicates with drifted
| thresholds between files (e.g. BSJP's take-profit was 3% in index.php
| but 4% in telegram_bot.php/bot_loop.php; Titan's volume-spike gate was
| 1.5x in titan_scan.php but 2.0x in telegram_bot.php's digest). Each
| strategy below has been reconciled to a single canonical rule set,
| consumed by StockScreenerService and TechnicalAnalysisService.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Realtime refresh cadence
    |--------------------------------------------------------------------------
    |
    | Cron expression for idx:update-realtime-quotes (see routes/console.php).
    | It is worth tuning because the command is cheap: realtimeScan() is a
    | single TradingView request covering the whole exchange, not one per
    | ticker, so the default five-minute cadence costs 12 requests an hour no
    | matter how many emiten are tracked. Use '* * * * *' for near-live quotes,
    | or back off if TradingView starts rate-limiting.
    |
    | (Written out in words rather than as a cron literal on purpose: a star
    | followed by a slash inside this comment would close it early.)
    |
    | This lives in config rather than being read with env() at the schedule
    | itself: once config:cache has run — which the container entrypoint does
    | on every boot — Laravel stops loading .env at all, and an env() call
    | outside a config file would silently fall back to its default.
    |
    */

    'realtime_cron' => env('IDX_REALTIME_CRON', '*/5 * * * *'),

    /*
    |--------------------------------------------------------------------------
    | Backtest universe
    |--------------------------------------------------------------------------
    |
    | How many tickers a backtest covers when none are named, ranked by traded
    | value. See BacktestEngine::defaultUniverse() for why this is capped at
    | all. 0 means every ticker that has price history.
    |
    */

    'backtest_universe' => (int) env('BACKTEST_UNIVERSE', 150),

    /*
    |--------------------------------------------------------------------------
    | Financial statement history
    |--------------------------------------------------------------------------
    |
    | How many fiscal years the stock detail page shows, and how many
    | idx:update-financials fetches. Five is enough to see a trend through a
    | full cycle without the table needing to scroll on a phone.
    |
    */

    'financial_statement_years' => (int) env('FINANCIAL_STATEMENT_YEARS', 5),


    'baseline' => [
        // Applied by every strategy before its own filters, matching the
        // `close_price > 50 AND volume > 0` guard repeated everywhere.
        'min_price' => 50,
    ],

    'magic_formula' => [
        'min_roe' => 0,
        'min_pe_ratio' => 0,
        'max_pe_ratio' => 50,
        'limit' => 30,
    ],

    'bandar_radar' => [
        'min_volume_spike_ratio' => 1.2,
        'min_transaction_value' => 2_000_000_000,
    ],

    'trend_runner' => [
        'min_rsi' => 50,
        'max_rsi' => 75,
    ],

    'pullback_sniper' => [
        'min_rsi' => 30,
        'max_rsi' => 55,
        'min_transaction_value' => 1_000_000_000,
    ],

    'undervalued' => [
        'min_roe' => 10,
        'max_der' => 1.0,
        'max_pb_ratio' => 1.5,
    ],

    'top_picks' => [
        'min_roe' => 10,
        'max_pe_ratio' => 25,
    ],

    'bsjp' => [
        // "Beli Sore Jual Pagi" — buy-the-evening-close, sell-the-morning-open.
        'min_close_vs_high_ratio' => 0.98,
        'min_volume_spike_ratio' => 1.25,
        'min_transaction_value' => 3_000_000_000,
        'take_profit_pct' => 0.03,
        'stop_loss_pct' => 0.02,
        // Dashboard BSJP-tab display order only (doesn't affect qualification).
        'sort_weight_volume_spike' => 10,
        'sort_weight_breakout' => 30,
        'sort_weight_above_vwap' => 20,
    ],

    'titan' => [
        'min_price' => 60,
        'min_transaction_value' => 2_000_000_000,
        'entry_volume_spike_ratio' => 1.5,
        'volume_spike_tiers' => [
            // ratio_gte => [points, tag]
            ['ratio' => 5.0, 'points' => 40, 'tag' => 'EXTREME VOL'],
            ['ratio' => 3.0, 'points' => 30, 'tag' => 'HUGE VOL'],
            ['ratio' => 1.5, 'points' => 20, 'tag' => null],
        ],
        'trend_points' => 15,
        'vwap_points' => 15,
        'breakout_points' => 20,
        'macd_points' => 5,
        'rsi_points' => 5,
        'rsi_min' => 50,
        'rsi_max' => 75,
        'qualify_score' => 50,
        'tier_s_score' => 85,
        'tier_a_score' => 70,
        'result_limit' => 50,
        'take_profit_1_pct' => 0.05,
        'take_profit_2_pct' => 0.10,
        'stop_loss_pct' => 0.04,
    ],

    'value_investing' => [
        // Telegram /INVEST command.
        'min_roe' => 10,
        'min_pe_ratio' => 0,
        'max_pe_ratio' => 15,
        'limit' => 5,
    ],

    'heatmap' => [
        // Which box-sizing metric the treemap uses depends on whether
        // market-cap data is actually populated for the universe (it may
        // not be, if UpdateFundamentals hasn't run yet) — decided once
        // for the whole render, not per-row. The legacy heatmap.php set
        // this flag inside its per-row loop and never reset it, so once
        // any single row had a large market cap, every row *after* it in
        // iteration order was silently held to a different (500B vs 1B)
        // inclusion threshold than the rows before it — a real bug, not
        // a deliberate rule; this config makes the (now dataset-wide,
        // consistent) thresholds explicit.
        'market_cap_probe_threshold' => 100_000_000_000,
        'min_market_cap' => 500_000_000_000,
        'min_transaction_value' => 1_000_000_000,
        'max_per_sector' => 20,
    ],

    'quadra_report' => [
        // Telegram broadcast digest: top N per category.
        'per_category_limit' => 3,
    ],

    /*
    |----------------------------------------------------------------------
    | Trading Plan Multipliers
    |----------------------------------------------------------------------
    |
    | Feed TechnicalAnalysisService::buildTradingPlan(). "swing" is the
    | pivot-point based plan used for the general dashboard and detail
    | page; "bsjp" is the fast overnight plan; "titan" is the
    | high-volatility momentum plan (fixed % targets, no pivot math).
    |
    */

    'trading_plan' => [
        'swing' => [
            'entry_band_pct' => 0.01,
            'stop_loss_buffer_pct' => 0.03,
            // Entering at market leaves the stop a pivot range plus this
            // buffer below, while R1 sits a fraction of that above — so the
            // first pivot target was losing on risk/reward in every session
            // measured. Below this ratio the plan reaches for R2 instead.
            // Set to 0 to always target R1, whatever the ratio.
            'min_risk_reward' => 1.0,
        ],
        'bsjp' => [
            'take_profit_pct' => 0.03,
            'stop_loss_pct' => 0.02,
        ],
        'titan' => [
            'stop_loss_pct' => 0.04,
            'take_profit_1_pct' => 0.05,
            'take_profit_2_pct' => 0.10,
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | AI Score Weights (0-100 composite used across dashboard/detail/bot)
    |----------------------------------------------------------------------
    */

    'score' => [
        'trend_above_ma20' => 20,
        'trend_above_vwap' => 15,
        'momentum_macd_positive' => 10,
        'momentum_rsi_sweet_spot' => 10,
        // Oversold only — see calculateScore(). This was 'momentum_rsi_extreme'
        // and paid out at both ends of the range, which rewarded an overbought
        // stock for being overbought.
        'momentum_rsi_oversold' => 5,
        'momentum_stoch_oversold' => 5,
        // One graded volume award replacing the old two-step pair; the sum is
        // unchanged, so the flow maximum is still 25.
        'flow_volume' => 15,
        'flow_volume_above_avg' => 10,
        'flow_volume_spike' => 5,
        'flow_breakout' => 10,
        'fundamental_roe' => 5,
        'fundamental_der' => 5,
        'fundamental_per' => 5,
        /*
        | Ramp endpoints for the graded awards.
        |
        | Each pair is (no credit at, full credit at) for one rule. The old
        | scoring was entirely all-or-nothing, which meant the 0-100 scale
        | could only ever land on 21 values — every multiple of 5 — so a
        | ranking of 900 emiten collapsed into 21 buckets and two stocks were
        | indistinguishable unless they differed on a whole condition. Between
        | the two endpoints credit is now linear, and outside them it clamps,
        | so the old thresholds still mark where credit starts and stops.
        |
        | Written as ratios where the underlying figure scales with price
        | (a MACD histogram of 5 means something different on a Rp150 stock
        | than on a Rp9,000 one).
        */
        'ma20_premium_full' => 0.03,      // 3% above MA20 earns the full trend award
        'vwap_premium_full' => 0.02,
        'macd_hist_ratio_full' => 0.005,  // hist/close
        'rsi_approach_from' => 40,        // credit ramps in from here up to the sweet spot
        'stoch_full_at' => 0.0,           // %K 0 is maximum oversold
        'volume_ratio_full' => 2.0,
        'fundamental_roe_full' => 25,
        'fundamental_der_full' => 0.0,    // debt-free earns the whole award
        'fundamental_per_full' => 8,

        'rsi_sweet_spot_min' => 50,
        'rsi_sweet_spot_max' => 70,
        // Were hard-coded in calculateScore(), which is what this file exists
        // to prevent. rsi_oversold_max is the only one still consulted:
        // above rsi_sweet_spot_max now scores nothing at all.
        'rsi_oversold_max' => 30,
        'stoch_oversold_max' => 20,
        'volume_above_avg_ratio' => 1.2,
        'volume_spike_ratio' => 2.0,
        'fundamental_roe_min' => 10,
        'fundamental_der_max' => 1.5,
        'fundamental_per_max' => 25,
        'verdict_strong_buy' => 80,
        'verdict_buy' => 60,
        'verdict_neutral' => 40,
    ],

];
