<?php

use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // In production the app sits behind the Caddy container, which
        // terminates TLS and forwards plain http over the container network.
        // Without trusting its X-Forwarded-* headers Laravel believes every
        // request is insecure: url()/route() emit http:// links on an https://
        // site, the post-login redirect breaks, and $request->ip() logs the
        // proxy instead of the visitor.
        //
        // This closure runs before .env is parsed, so env() here only sees
        // real environment variables — docker-compose.yml passes
        // TRUSTED_PROXIES through for exactly that reason. The fallback covers
        // any other container runtime; it is limited to RFC1918 space so a
        // forged header arriving straight off the public internet is ignored.
        $middleware->trustProxies(
            at: array_values(array_filter(array_map('trim', explode(
                ',',
                (string) env('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')
            )))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // The Telegram webhook is authenticated via the secret-token header
        // (see VerifyTelegramWebhookSecret), not a session, so it is exempt
        // from CSRF like any other unauthenticated inbound webhook.
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
        ]);

        $middleware->alias([
            'telegram.webhook' => VerifyTelegramWebhookSecret::class,
            'subscription.active' => EnsureSubscriptionActive::class,
            'plan' => EnsurePlanFeature::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
