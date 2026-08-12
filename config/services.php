<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot
    |--------------------------------------------------------------------------
    |
    | Credentials for the IDX Invest Telegram bot. The webhook_secret is an
    | arbitrary string you also set on Telegram via setWebhook's
    | secret_token parameter; Telegram echoes it back on every request in
    | the X-Telegram-Bot-Api-Secret-Token header so we can verify inbound
    | webhook calls actually came from Telegram. See
    | VerifyTelegramWebhookSecret.
    |
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),
        'broadcast_chat_ids' => array_filter(array_map(
            'trim',
            explode(',', (string) env('TELEGRAM_BROADCAST_CHAT_IDS', ''))
        )),
        // Without the @ prefix, e.g. "IdxInvestBot" — used only to build the
        // t.me/<username>?start=... one-tap link on the "Pengaturan
        // Telegram" page. The /LINK <code> flow works without this too.
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        // "-latest" aliases always point at Google's current recommended
        // model for that tier, so this stops going stale the way a pinned
        // version (e.g. gemini-2.0-flash, retired mid-2026) eventually
        // does. Override with a pinned version in .env if you need
        // reproducible output instead of "whatever's current".
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),
        // Short, decisive buy/sell reads rather than essays: low temperature
        // because this is meant to summarise numbers it was given, and a token
        // cap because the answer lands in a Telegram bubble or a modal.
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.3),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Yahoo Finance
    |--------------------------------------------------------------------------
    |
    | verify_ssl should stay true in production. It only exists because the
    | legacy app ran on a local XAMPP stack with an outdated CA bundle and
    | disabled verification globally; expose the same escape hatch here,
    | scoped to this integration only, rather than disabling curl SSL
    | verification everywhere.
    |
    */

    'yahoo_finance' => [
        'base_url' => env('YAHOO_FINANCE_BASE_URL', 'https://query1.finance.yahoo.com'),
        'crumb_base_url' => env('YAHOO_FINANCE_CRUMB_BASE_URL', 'https://query2.finance.yahoo.com'),
        'auth_cookie_url' => env('YAHOO_FINANCE_AUTH_COOKIE_URL', 'https://fc.yahoo.com'),
        'user_agent' => env(
            'YAHOO_FINANCE_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ),
        'verify_ssl' => (bool) env('YAHOO_FINANCE_VERIFY_SSL', true),
        'timeout' => (int) env('YAHOO_FINANCE_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | TradingView (undocumented scanner endpoint)
    |--------------------------------------------------------------------------
    */

    'tradingview' => [
        'scanner_url' => env('TRADINGVIEW_SCANNER_URL', 'https://scanner.tradingview.com/indonesia/scan'),
        'user_agent' => env(
            'TRADINGVIEW_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ),
        'verify_ssl' => (bool) env('TRADINGVIEW_VERIFY_SSL', true),
        'timeout' => (int) env('TRADINGVIEW_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | IDX News Feeds
    |--------------------------------------------------------------------------
    */

    'idx_news' => [
        'user_agent' => env(
            'IDX_NEWS_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ),
        'feeds' => [
            ['name' => 'CNBC Market', 'url' => 'https://www.cnbcindonesia.com/market/rss', 'color' => 'primary'],
            ['name' => 'Kontan Investasi', 'url' => 'https://investasi.kontan.co.id/rss', 'color' => 'success'],
            ['name' => 'CNBC Investment', 'url' => 'https://www.cnbcindonesia.com/investment/rss', 'color' => 'info'],
        ],
        // env('KEY', $default) only falls back to $default when the key is
        // completely absent — an explicitly blank `IDX_SENTIMENT_FEEDS=`
        // line in .env (which .env.example ships, as a place to override
        // it) still counts as "present" and silently produced an empty
        // feed list. `?: $default` treats blank the same as unset.
        'sentiment_feeds' => array_filter(array_map('trim', explode(',', (string) (env('IDX_SENTIMENT_FEEDS') ?: (
            'https://www.cnbcindonesia.com/market/rss,https://www.kontan.co.id/rss/investasi,https://finance.detik.com/bursa-dan-valas/rss,https://www.cnnindonesia.com/ekonomi/rss,https://investor.id/rss/market-and-corporate'
        ))))),
    ],

];
