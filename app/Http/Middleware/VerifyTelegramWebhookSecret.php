<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telegram echoes back the secret_token you registered via setWebhook in
 * the X-Telegram-Bot-Api-Secret-Token header on every webhook call. This
 * is the only thing standing between the public webhook route and anyone
 * on the internet who finds the URL — the legacy webhook.php had no such
 * check at all.
 */
class VerifyTelegramWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.telegram.webhook_secret');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(403, 'Invalid Telegram webhook secret token.');
        }

        return $next($request);
    }
}
