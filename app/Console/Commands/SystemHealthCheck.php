<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Services\MarketData\YahooFinanceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Consolidates four legacy one-off diagnostic pages — cek_sistem.php,
 * debug_token.php, tes_koneksi.php and tes_google.php — into a single
 * Artisan command instead of publicly-reachable debug routes.
 */
class SystemHealthCheck extends Command
{
    protected $signature = 'idx:health-check {--list-models : List the Gemini models this API key may use, then exit}';

    protected $description = 'Diagnose DB connectivity, Yahoo Finance auth, Gemini API key and Telegram bot token';

    /** @var array<int, string> Detail for the check currently running. */
    private array $notes = [];

    public function __construct(private readonly YahooFinanceClient $yahoo)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('list-models')) {
            return $this->listModels();
        }

        $checks = [
            'Database connection' => fn (): bool => $this->checkDatabase(),
            'stock_prices has history_json data' => fn (): bool => $this->checkHistoryData(),
            'Yahoo Finance crumb/cookie auth' => fn (): bool => $this->checkYahooCrumb(),
            'Gemini API key' => fn (): bool => $this->checkGemini(),
            'Telegram bot token' => fn (): bool => $this->checkTelegram(),
        ];

        $failed = false;

        foreach ($checks as $label => $check) {
            // Deferred rather than printed as it happens, so a check's detail
            // appears under its own label instead of above the whole summary.
            $this->notes = [];

            $ok = $check();

            $ok ? $this->components->info($label) : $this->components->error($label);

            foreach ($this->notes as $note) {
                $this->line("  <fg=gray>{$note}</>");
            }

            $failed = $failed || ! $ok;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkHistoryData(): bool
    {
        // Uncaught, a database that is down took the whole command out with a
        // stack trace — including the Gemini and Telegram checks, which do not
        // need the database at all. A diagnostic command has to survive the
        // things it exists to diagnose.
        try {
            return StockPrice::query()->whereNotNull('history_json')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function checkYahooCrumb(): bool
    {
        try {
            return $this->yahoo->checkAuthentication();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Listing /models only proves the key is live. It does not prove the app
     * can generate anything: a valid key still fails if GEMINI_MODEL names a
     * model that does not exist, or if the request body carries a field this
     * model generation rejects. So make the same call the app makes, and
     * print what came back — the point of this command is to answer "why",
     * not to restate that something is broken.
     */
    private function checkGemini(): bool
    {
        $key = config('services.gemini.api_key');
        if (empty($key)) {
            $this->notes[] = 'GEMINI_API_KEY is empty — AI features are off.';

            return false;
        }

        $model = config('services.gemini.model');

        $thinkingBudget = (int) config('services.gemini.thinking_budget');

        $generationConfig = ['maxOutputTokens' => (int) config('services.gemini.max_output_tokens')];

        if ($thinkingBudget >= 0) {
            $generationConfig['thinkingConfig'] = ['thinkingBudget' => $thinkingBudget];
        }

        try {
            // Key in the header, never as ?key= — a connection failure puts the
            // effective URL into the exception message, and this command's
            // output tends to end up pasted into chats and issue trackers.
            $response = Http::timeout(15)
                ->withHeaders(['x-goog-api-key' => $key])
                ->post(config('services.gemini.base_url')."/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => 'Balas dengan satu kata: OK']]]],
                    'generationConfig' => $generationConfig,
                ]);
        } catch (Throwable $e) {
            $this->notes[] = 'Could not reach Gemini: '.$e->getMessage();

            return false;
        }

        if ($response->successful()) {
            $this->notes[] = "model {$model} answered normally";

            return true;
        }

        $this->notes[] = sprintf(
            'HTTP %d from model "%s": %s',
            $response->status(),
            $model,
            $response->json('error.message') ?? 'no error message',
        );

        if ($response->status() === 404) {
            $this->notes[] = 'A 404 means the model name is wrong. Pick one from:';
            $this->notes[] = '  php artisan idx:health-check --list-models';
        }

        if ($response->status() === 400) {
            $this->notes[] = 'A 400 is usually thinkingConfig on a model that predates it —';
            $this->notes[] = 'set GEMINI_THINKING_BUDGET=-1 to stop sending that field.';
        }

        return false;
    }

    /**
     * Which model names this key may actually use — the answer to a 404
     * above, and not something worth guessing at from documentation.
     */
    private function listModels(): int
    {
        $key = config('services.gemini.api_key');

        if (empty($key)) {
            $this->components->error('GEMINI_API_KEY is empty.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['x-goog-api-key' => $key])
                ->get(config('services.gemini.base_url').'/models');
        } catch (Throwable $e) {
            $this->components->error('Could not reach Gemini: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->components->error(sprintf(
                'HTTP %d: %s',
                $response->status(),
                $response->json('error.message') ?? 'no error message',
            ));

            return self::FAILURE;
        }

        $current = config('services.gemini.model');

        foreach ($response->json('models') ?? [] as $entry) {
            // The API returns "models/<name>"; GEMINI_MODEL holds just <name>.
            $name = str_replace('models/', '', $entry['name'] ?? '');

            if (! in_array('generateContent', $entry['supportedGenerationMethods'] ?? [], true)) {
                continue;
            }

            $this->line($name === $current ? "  <fg=green>{$name}  ← GEMINI_MODEL</>" : "  {$name}");
        }

        return self::SUCCESS;
    }

    private function checkTelegram(): bool
    {
        $token = config('services.telegram.bot_token');
        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            return $response->successful() && $response->json('ok') === true;
        } catch (Throwable) {
            return false;
        }
    }
}
