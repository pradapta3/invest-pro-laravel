<?php

namespace App\Services;

use App\ValueObjects\ProphetForecast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wrapper around the Google Gemini API, replacing the askGemini()
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
                // The key goes in a header, not ?key= in the URL. Guzzle puts the
                // full effective URL into its ConnectionException message, so with
                // the key as a query parameter every timeout or reset wrote the
                // live API key into the log — and the catch below handed that same
                // message back to the user, so it also appeared in Telegram
                // replies and the dashboard modal.
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    // Left unset, output length is bounded only by the model's
                    // maximum, and the default temperature is tuned for
                    // open-ended writing. These answers are short buy/sell reads
                    // shown in a Telegram bubble or a modal, so cap the length and
                    // ask for the least improvisation the model will give.
                    'generationConfig' => [
                        'temperature' => (float) config('services.gemini.temperature'),
                        'maxOutputTokens' => (int) config('services.gemini.max_output_tokens'),
                    ],
                ]);
        } catch (Throwable $e) {
            // Detail to the log, not to whoever asked: the message can carry the
            // request URL and other internals.
            Log::error('Gemini request failed', ['error' => $e->getMessage()]);

            return '⚠️ Koneksi ke AI gagal. Coba lagi sebentar lagi.';
        }

        if ($response->failed()) {
            Log::warning('Gemini returned an error', [
                'status' => $response->status(),
                'message' => $response->json('error.message') ?? 'Unknown error',
            ]);

            return '⚠️ AI sedang tidak bisa dihubungi. Coba lagi sebentar lagi.';
        }

        // A prompt rejected by the safety filters comes back 200 with no
        // candidates at all, and a truncated answer comes back looking complete.
        // Both used to surface as the same bare "AI tidak merespon", which is
        // impossible to act on.
        if ($blockReason = $response->json('promptFeedback.blockReason')) {
            Log::warning('Gemini blocked the prompt', ['reason' => $blockReason]);

            return '⚠️ Permintaan ditolak filter keamanan AI.';
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $finishReason = $response->json('candidates.0.finishReason');

        if ($text === null) {
            Log::warning('Gemini returned no text', ['finish_reason' => $finishReason]);

            return '⚠️ AI tidak merespon.';
        }

        if ($finishReason === 'MAX_TOKENS') {
            // Say so rather than presenting a sentence that stops mid-word as
            // the model's conclusion. No markdown emphasis around it: this
            // string is pasted into Telegram HTML messages and Markdown ones
            // alike, and shows up as literal underscores in the former.
            return rtrim($text)."\n\n(jawaban terpotong — batas panjang)";
        }

        return $text;
    }

    /**
     * Prompt used for the "Prophet" trend-analysis feature (Telegram
     * /PROPHET command and the dashboard's quant-scan AI button).
     */
    public function analyzeProphetForecast(string $ticker, ProphetForecast $forecast): string
    {
        // Every field prophetTrend() computed, not five of nine. The old prompt
        // dropped RSI, volatility, support and resistance and then asked for a
        // buy/sell call — withholding the momentum reading and the two levels
        // that decide where to enter and where to cut. Units and the forecast
        // horizon are spelled out too: "Slope 12.4" on its own says nothing,
        // and the numbers go in unseparated so "4,500" can't be read as 4.5.
        $data = [
            'Harga penutupan terakhir' => 'Rp '.round($forecast->lastPrice),
            'Tren' => $forecast->status.' (kekuatan: '.$forecast->strength.')',
            'Kemiringan regresi' => 'Rp '.$forecast->slope.' per hari bursa',
            'RSI(14)' => $forecast->rsi,
            'Deviasi standar harga' => 'Rp '.$forecast->standardDeviation,
            'Proyeksi harga hari bursa berikutnya' => 'Rp '.round($forecast->forecast),
            // Decimal points, not commas, throughout: the values interpolated
            // below are PHP floats and print with a point, and mixing the two
            // conventions in one list of numbers is exactly the ambiguity the
            // unseparated thousands are there to avoid.
            'Support (proyeksi - 1.5 deviasi)' => 'Rp '.round($forecast->support),
            'Resistance (proyeksi + 1.5 deviasi)' => 'Rp '.round($forecast->resistance),
        ];

        $lines = [
            "Kamu analis teknikal saham Bursa Efek Indonesia. Berikut hasil regresi linear atas maksimal 30 harga penutupan terakhir emiten {$ticker}:",
            '',
        ];

        foreach ($data as $label => $value) {
            $lines[] = "- {$label}: {$value}";
        }

        $lines[] = '';
        $lines[] = 'Berikan rekomendasi Buy, Sell, atau Hold beserta alasannya dalam maksimal 3 kalimat, '
            .'hanya berdasarkan angka di atas — jangan mengarang data lain. Sebutkan level entry dan '
            .'stop loss dari angka support/resistance tersebut.';
        $lines[] = $this->outputContract();

        return $this->generate(implode("\n", $lines));
    }

    /**
     * Fallback free-form analysis when a ticker has no local price data —
     * mirrors bot_loop.php asking Gemini directly about an unknown code.
     */
    public function analyzeUnknownTicker(string $ticker): string
    {
        // This branch runs precisely because the app has no prices for the
        // code, so "Analisa singkat saham X hari ini" invited the model to
        // invent today's price and a target for it — presented to the user
        // under a "🧠 AI:" heading with nothing marking it as guesswork.
        return $this->generate(
            "Kode {$ticker} tidak ada di basis data harga kami. Jika kamu mengenalinya sebagai emiten "
            .'Bursa Efek Indonesia, jelaskan bidang usahanya dalam maksimal 2 kalimat. Jika tidak, katakan '
            .'saja kode itu tidak dikenali. Jangan menyebut harga, target harga, atau rekomendasi beli/jual: '
            .'kamu tidak punya data harga terkini untuk emiten ini.'
            ."\n".$this->outputContract()
        );
    }

    /**
     * Appended to every prompt this app composes. The answer is pasted into a
     * Telegram message sent with parse_mode set, and Telegram rejects the
     * *whole* message with 400 when the text has an unbalanced entity — so a
     * single stray asterisk in the model's prose costs the user the reply.
     * TelegramBotService::sendMessage() also retries in plain text; this is
     * the cheaper half of the same fix.
     */
    private function outputContract(): string
    {
        return 'Jawab dalam Bahasa Indonesia sebagai teks biasa, tanpa format markdown (tanpa tanda *, _, ` atau #).';
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
