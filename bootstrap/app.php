<?php

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The Telegram webhook is authenticated via the secret-token header
        // (see VerifyTelegramWebhookSecret), not a session, so it is exempt
        // from CSRF like any other unauthenticated inbound webhook.
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
        ]);

        $middleware->alias([
            'telegram.webhook' => VerifyTelegramWebhookSecret::class,
            'subscription.active' => EnsureSubscriptionActive::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
