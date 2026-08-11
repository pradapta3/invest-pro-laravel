<?php

namespace App\Services\MarketData;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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
    /**
     * How many transport failures in a row before this instance stops trying.
     *
     * Not one: idx:update-market-data loops every tracked ticker on a single
     * injected instance, and a lone blip on the first ticker must not skip the
     * other nine hundred. Not many either: PortfolioService::holdings() calls
     * chart() once per position whose stock_prices row has no usable close, so
     * with Yahoo unreachable each attempt costs a full
     * services.yahoo_finance.timeout. Fifteen holdings meant fifteen sequential
     * timeouts — past nginx's fastcgi_read_timeout and php-fpm's
     * request_terminate_timeout, so the page 504'd while holding one of only
     * twenty workers for over two minutes. Three bounds that at roughly one
     * timeout's worth of waiting.
     */
    private const MAX_CONSECUTIVE_FAILURES = 3;

    private ?string $crumb = null;

    private ?CookieJar $cookieJar = null;

    private int $consecutiveFailures = 0;

    public function chart(string $ticker, string $range = '1mo', string $interval = '1d'): array
    {
        $ticker = $this->normalizeTicker($ticker);
        $url = config('services.yahoo_finance.base_url')."/v8/finance/chart/{$ticker}";

        $response = $this->attempt('chart', $ticker, fn () => $this->client()->get($url, [
            'range' => $range,
            'interval' => $interval,
        ]));

        return $response?->json('chart.result.0') ?? [];
    }

    public function quoteSummary(string $ticker, array $modules = ['financialData', 'defaultKeyStatistics']): array
    {
        $ticker = $this->normalizeTicker($ticker);
        $url = config('services.yahoo_finance.base_url')."/v10/finance/quoteSummary/{$ticker}";

        // The whole call, crumb handshake included, sits inside the closure:
        // crumb() and cookieJar() make upstream requests of their own, and as
        // arguments they would otherwise be evaluated outside any guard.
        $response = $this->attempt('quoteSummary', $ticker, fn () => $this->authenticatedClient()->get($url, [
            'modules' => implode(',', $modules),
            'crumb' => $this->crumb(),
        ]));

        return $response?->json('quoteSummary.result.0') ?? [];
    }

    public function realtimeQuote(string $ticker): array
    {
        $ticker = $this->normalizeTicker($ticker);
        $url = config('services.yahoo_finance.base_url').'/v7/finance/quote';

        $response = $this->attempt('realtimeQuote', $ticker, fn () => $this->authenticatedClient()->get($url, [
            'symbols' => $ticker,
            'crumb' => $this->crumb(),
        ]));

        return $response?->json('quoteResponse.result.0') ?? [];
    }

    /**
     * Runs one upstream call and reduces every way it can fail to null.
     *
     * An error *status* was always handled here; a transport failure — DNS,
     * TLS, connection reset, or services.yahoo_finance.timeout expiring —
     * throws instead, and used to escape to the caller. That took the whole
     * dashboard down with it, because DashboardController reaches chart()
     * synchronously through MarketDataService::indexQuote() while rendering
     * the landing page. Every caller already treats an empty result as "no
     * data" (indexQuote returns null, and layouts/app.blade.php renders the
     * IHSG ticker only @if($ihsg)), so a dead upstream degrades to that.
     */
    private function attempt(string $context, string $ticker, callable $call): ?Response
    {
        // Upstream has failed to answer MAX_CONSECUTIVE_FAILURES times running,
        // so stop paying a timeout per call for the rest of this request or
        // command run.
        if ($this->consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
            return null;
        }

        try {
            $response = $call();
        } catch (Throwable $e) {
            $this->consecutiveFailures++;

            Log::channel('market_data')->warning("Yahoo {$context} request failed", [
                'ticker' => $ticker,
                'error' => $e->getMessage(),
                'consecutive_failures' => $this->consecutiveFailures,
                'giving_up' => $this->consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES,
            ]);

            return null;
        }

        // Any answer at all — including an error status — proves the connection
        // works, so the run of failures is over. Without this reset, occasional
        // blips spread across a long batch would eventually add up and stop it.
        $this->consecutiveFailures = 0;

        if (! $response->successful()) {
            Log::channel('market_data')->warning("Yahoo {$context} request failed", [
                'ticker' => $ticker,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response;
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
            // crumb() validates and throws on anything unusable, so reaching
            // here at all means the handshake produced a real token.
            $this->crumb();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function normalizeTicker(string $ticker): string
    {
        $ticker = strtoupper(trim($ticker));

        // Yahoo prefixes index symbols with ^ and they carry no exchange
        // suffix: the IHSG is ^JKSE, not ^JKSE.JK. Appending one produced a
        // symbol Yahoo does not know, so MarketDataService::indexQuote() — the
        // only caller that passes an index — could never return anything and
        // the IHSG figure in the dashboard header was permanently blank.
        if (str_starts_with($ticker, '^')) {
            return $ticker;
        }

        return str_contains($ticker, '.JK') ? $ticker : $ticker.'.JK';
    }

    /**
     * A crumb is a short opaque token. Anything else — an empty body, or the
     * HTML error page Yahoo serves when the session cookie was rejected — is
     * not one, and caching it would poison every later call on this instance.
     */
    private function looksLikeCrumb(string $candidate): bool
    {
        return $candidate !== '' && strlen($candidate) <= 20 && ! str_contains($candidate, '<html');
    }

    private function crumb(): string
    {
        if ($this->crumb !== null) {
            return $this->crumb;
        }

        $response = $this->authenticatedClient()
            ->get(config('services.yahoo_finance.crumb_base_url').'/v1/test/getcrumb');

        $crumb = trim($response->body());

        // Only a plausible crumb is remembered. Throwing on the rest means the
        // failure reaches attempt(), which logs it and counts it toward the
        // consecutive-failure limit rather than letting the caller carry on
        // with a token that cannot work — previously a single bad handshake
        // was cached for the life of the instance, so a batch over hundreds of
        // tickers returned nothing at all and still exited successfully.
        if (! $this->looksLikeCrumb($crumb)) {
            throw new RuntimeException('Yahoo returned no usable crumb (HTTP '.$response->status().').');
        }

        $this->crumb = $crumb;

        return $this->crumb;
    }

    private function cookieJar(): CookieJar
    {
        if ($this->cookieJar !== null) {
            return $this->cookieJar;
        }

        $jar = new CookieJar;

        // Prime the jar by visiting Yahoo's auth-cookie endpoint. This
        // must pass the `cookies` option itself (not just $this->client())
        // — Guzzle only writes a response's Set-Cookie headers into a jar
        // when that request explicitly hands it one. Without this, the
        // jar stayed permanently empty and every subsequent "authenticated"
        // request went out with no session cookie at all, so Yahoo's
        // getcrumb endpoint replied with {"error":{"code":"Unauthorized",
        // "description":"Invalid Cookie"}} instead of a real crumb.
        $this->client()->withOptions(['cookies' => $jar])
            ->get(config('services.yahoo_finance.auth_cookie_url'));

        // Assigned only once priming actually yielded cookies. The old code
        // assigned the empty jar first, so a priming request that failed left
        // a non-null but useless jar that was never rebuilt — every later
        // authenticated call went out unauthenticated and quietly returned
        // nothing.
        if ($jar->count() === 0) {
            throw new RuntimeException('Yahoo returned no session cookie.');
        }

        $this->cookieJar = $jar;

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
