<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Registers this deployment's Telegram webhook, replacing the hand-run curl
 * the deployment notes used to carry.
 *
 * Doing it here rather than by hand buys three things the curl could not: the
 * URL comes from the route rather than being retyped (so it always matches
 * APP_URL and the actual route path), the secret is read from config rather
 * than scraped out of .env with grep, and the two states that produce a
 * webhook which looks registered but silently rejects everything are refused
 * up front instead of discovered later.
 */
class SetTelegramWebhook extends Command
{
    protected $signature = 'idx:telegram-webhook
                            {--show : Report what Telegram currently has registered, and change nothing}
                            {--delete : Remove the registered webhook}';

    protected $description = 'Register, inspect or remove the Telegram webhook for this deployment';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            $this->components->error('TELEGRAM_BOT_TOKEN is empty — nothing to register. Set it in .env, then re-run.');

            return self::FAILURE;
        }

        if ($this->option('show')) {
            return $this->report($token);
        }

        if ($this->option('delete')) {
            $response = Http::timeout(10)->post($this->endpoint($token, 'deleteWebhook'));

            if (! $response->successful() || ! $response->json('ok')) {
                $this->components->error('deleteWebhook failed: '.$response->json('description', $response->body()));

                return self::FAILURE;
            }

            $this->components->info('Webhook removed.');

            return self::SUCCESS;
        }

        $secret = (string) config('services.telegram.webhook_secret');

        // Registering without a secret would produce the worst possible state:
        // Telegram happily delivers updates and VerifyTelegramWebhookSecret
        // rejects every one of them with a 403, because it treats an empty
        // expected secret as "deny". The bot would look connected and answer
        // nothing.
        if ($secret === '') {
            $this->components->error('TELEGRAM_WEBHOOK_SECRET is empty. The webhook route rejects every request when it is blank, so registering now would give you a bot that never replies.');
            $this->line('  Generate one with: <fg=cyan>openssl rand -hex 32</>');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');

        // Telegram refuses plain http outright. Catching it here explains the
        // real cause — usually APP_URL still pointing at localhost — instead of
        // leaving the operator with Telegram's terse "bad webhook" reply.
        if (! str_starts_with($url, 'https://')) {
            $this->components->error("Telegram only accepts an https webhook, but APP_URL produces: {$url}");

            return self::FAILURE;
        }

        $response = Http::timeout(10)->post($this->endpoint($token, 'setWebhook'), [
            'url' => $url,
            'secret_token' => $secret,
        ]);

        if (! $response->successful() || ! $response->json('ok')) {
            $this->components->error('setWebhook failed: '.$response->json('description', $response->body()));

            return self::FAILURE;
        }

        $this->components->info("Webhook registered: {$url}");

        return $this->report($token);
    }

    private function report(string $token): int
    {
        $info = Http::timeout(10)->get($this->endpoint($token, 'getWebhookInfo'));

        if (! $info->successful() || ! $info->json('ok')) {
            $this->components->error('getWebhookInfo failed: '.$info->json('description', $info->body()));

            return self::FAILURE;
        }

        $result = $info->json('result', []);
        $registered = (string) ($result['url'] ?? '');
        $expected = route('telegram.webhook');

        $this->components->twoColumnDetail('URL', $registered !== '' ? $registered : '<fg=yellow>(none)</>');
        $this->components->twoColumnDetail('Pending updates', (string) ($result['pending_update_count'] ?? 0));
        $this->components->twoColumnDetail('Custom secret set', ($result['has_custom_certificate'] ?? false) ? 'n/a' : (($result['url'] ?? '') !== '' ? 'yes (Telegram does not echo it back)' : 'no'));

        if (isset($result['last_error_message'])) {
            $this->components->twoColumnDetail('Last error', '<fg=red>'.$result['last_error_message'].'</>');
        }

        if ($registered === '') {
            $this->components->warn('No webhook registered — run this command without --show to register one.');

            return self::FAILURE;
        }

        if ($registered !== $expected) {
            $this->components->warn("Registered URL does not match this deployment ({$expected}). Re-run without --show to point it here.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function endpoint(string $token, string $method): string
    {
        return "https://api.telegram.org/bot{$token}/{$method}";
    }
}
