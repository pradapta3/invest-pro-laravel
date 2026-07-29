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
    protected $signature = 'idx:health-check';

    protected $description = 'Diagnose DB connectivity, Yahoo Finance auth, Gemini API key and Telegram bot token';

    public function __construct(private readonly YahooFinanceClient $yahoo)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $results = [
            'Database connection' => $this->checkDatabase(),
            'stock_prices has history_json data' => $this->checkHistoryData(),
            'Yahoo Finance crumb/cookie auth' => $this->checkYahooCrumb(),
            'Gemini API key' => $this->checkGemini(),
            'Telegram bot token' => $this->checkTelegram(),
        ];

        foreach ($results as $label => $ok) {
            $ok ? $this->components->info($label) : $this->components->error($label);
        }

        return in_array(false, $results, true) ? self::FAILURE : self::SUCCESS;
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
        return StockPrice::query()->whereNotNull('history_json')->exists();
    }

    private function checkYahooCrumb(): bool
    {
        try {
            return $this->yahoo->checkAuthentication();
        } catch (Throwable) {
            return false;
        }
    }

    private function checkGemini(): bool
    {
        $key = config('services.gemini.api_key');
        if (empty($key)) {
            return false;
        }

        try {
            $response = Http::timeout(10)->get(config('services.gemini.base_url').'/models', ['key' => $key]);

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
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
