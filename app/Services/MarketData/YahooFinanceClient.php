<?php

namespace App\Services\MarketData;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to Yahoo Finance's unofficial JSON endpoints.
 *
 * Two access patterns exist, both present in the legacy app:
 *
 *  - The `v8/finance/chart` endpoint needs no authentication and is used
 *    for OHLCV history (see legacy update_market.php, detail.php,
 *    portfolio.php, seasonality.php — all of which duplicated this call).
 *  - `quoteSummary` (fundamentals) and `v7/finance/quote` (realtime quote)
 *    require a "crumb" token paired with a session cookie, fetched once
 *    per client instance (see legacy yahoo_sys.php). The crumb/cookie pair
 *    is cached on this instance only — inject one instance per
 *    request/command run (Laravel's default constructor-injection
 *    behaviour) rather than resolving it fresh per call, so a batch job
 *    looping over hundreds of tickers authenticates once.
 */
class YahooFinanceClient
{
    private ?string $crumb = null;

    private ?CookieJar $cookieJar = null;

    public function chart(string $ticker, string $range = '1mo', string $interval = '1d'): array
    {
        $ticker = $this->normalizeTicker($ticker);
        $url = config('services.yahoo_finance.base_url')."/v8/finance/chart/{$ticker}";

        $response = $this->client()->get($url, [
            'range' => $range,
            'interval' => $interval,
        ]);

        if (! $response->successful()) {
            Log::channel('market_data')->warning('Yahoo chart request failed', [
                'ticker' => $ticker,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $response->json('chart.result.0') ?? [];
    }

    public function quoteSummary(string $ticker, array $modules = ['financialData', 'defaultKeyStatistics']): array
    {
        $ticker = $this->normalizeTicker($ticker);
        $url = config('services.yahoo_finance.base_url')."/v10/finance/quoteSummary/{$ticker}";

        $response = $this->authenticatedClient()->get($url, [
            'modules' => implode(',', $modules),
            'crumb' => $this->crumb(),
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json('quoteSummary.result.0') ?? [];
    }

    public function realtimeQuote(string $ticker): array
    {
        $ticker = $this->normalizeTicker($ticker);
        $url = config('services.yahoo_finance.base_url').'/v7/finance/quote';

        $response = $this->authenticatedClient()->get($url, [
            'symbols' => $ticker,
            'crumb' => $this->crumb(),
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json('quoteResponse.result.0') ?? [];
    }

    /**
     * True if Yahoo's crumb/cookie handshake produced something that
     * looks like a real crumb token rather than an HTML error page —
     * replaces the manual eyeballing debug_token.php asked the operator
     * to do.
     */
    public function checkAuthentication(): bool
    {
        try {
            $crumb = $this->crumb();
        } catch (\Throwable) {
            return false;
        }

        return $crumb !== '' && strlen($crumb) <= 20 && ! str_contains($crumb, '<html');
    }

    public function normalizeTicker(string $ticker): string
    {
        $ticker = strtoupper(trim($ticker));

        return str_contains($ticker, '.JK') ? $ticker : $ticker.'.JK';
    }

    private function crumb(): string
    {
        if ($this->crumb !== null) {
            return $this->crumb;
        }

        $response = $this->authenticatedClient()
            ->get(config('services.yahoo_finance.crumb_base_url').'/v1/test/getcrumb');

        $this->crumb = trim($response->body());

        return $this->crumb;
    }

    private function cookieJar(): CookieJar
    {
        if ($this->cookieJar !== null) {
            return $this->cookieJar;
        }

        $this->cookieJar = new CookieJar;

        // Prime the jar by visiting Yahoo's auth-cookie endpoint. This
        // must pass the `cookies` option itself (not just $this->client())
        // — Guzzle only writes a response's Set-Cookie headers into a jar
        // when that request explicitly hands it one. Without this, the
        // jar stayed permanently empty and every subsequent "authenticated"
        // request went out with no session cookie at all, so Yahoo's
        // getcrumb endpoint replied with {"error":{"code":"Unauthorized",
        // "description":"Invalid Cookie"}} instead of a real crumb.
        $this->client()->withOptions(['cookies' => $this->cookieJar])
            ->get(config('services.yahoo_finance.auth_cookie_url'));

        return $this->cookieJar;
    }

    private function authenticatedClient()
    {
        return $this->client()->withOptions(['cookies' => $this->cookieJar()]);
    }

    private function client()
    {
        return Http::withHeaders(['User-Agent' => config('services.yahoo_finance.user_agent')])
            ->timeout(config('services.yahoo_finance.timeout'))
            ->withOptions(['verify' => config('services.yahoo_finance.verify_ssl')]);
    }
}
