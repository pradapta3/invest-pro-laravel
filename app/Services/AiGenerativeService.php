<?php

namespace App\Services;

use App\ValueObjects\ProphetForecast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wrapper around the Google Gemini 2.0 API, replacing the askGemini()
 * duplicates in bot_loop.php and quant_scan.php. Never throws to callers —
 * like the legacy implementation, a failure produces a friendly fallback
 * string so a flaky AI call degrades a Telegram reply or modal instead of
 * breaking the request; failures are logged for operators separately.
 */
class AiGenerativeService
{
    public function generate(string $prompt): string
    {
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            Log::warning('Gemini API key is not configured.');

            return '⚠️ AI belum dikonfigurasi (GEMINI_API_KEY kosong).';
        }

        $model = config('services.gemini.model');
        $url = config('services.gemini.base_url')."/models/{$model}:generateContent";

        try {
            $response = Http::timeout(config('services.gemini.timeout'))
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url.'?key='.$apiKey, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                ]);
        } catch (Throwable $e) {
            Log::error('Gemini request failed', ['error' => $e->getMessage()]);

            return '⚠️ Koneksi ke AI gagal: '.$e->getMessage();
        }

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'Unknown error';
            Log::warning('Gemini returned an error', ['message' => $message]);

            return "⚠️ Google Error: {$message}";
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        return $text ?? '⚠️ AI tidak merespon.';
    }

    /**
     * Prompt used for the "Prophet" trend-analysis feature (Telegram
     * /PROPHET command and the dashboard's quant-scan AI button).
     */
    public function analyzeProphetForecast(string $ticker, ProphetForecast $forecast): string
    {
        $prompt = sprintf(
            'Analisa saham %s (Indonesia). Data matematis: Harga %s, Tren %s (Slope %s), Prediksi Math %s. '.
            'Berikan rekomendasi singkat padat (Buy/Sell) dan alasannya.',
            $ticker,
            number_format($forecast->lastPrice),
            $forecast->status,
            $forecast->slope,
            number_format($forecast->forecast),
        );

        return $this->generate($prompt);
    }

    /**
     * Fallback free-form analysis when a ticker has no local price data —
     * mirrors bot_loop.php asking Gemini directly about an unknown code.
     */
    public function analyzeUnknownTicker(string $ticker): string
    {
        return $this->generate("Analisa singkat saham {$ticker} hari ini.");
    }

    /**
     * Free-form chat fallback for the Telegram bot when no command
     * matched (bot_loop.php's catch-all "Chat Bebas" branch).
     */
    public function chat(string $message): string
    {
        return $this->generate($message);
    }
}
