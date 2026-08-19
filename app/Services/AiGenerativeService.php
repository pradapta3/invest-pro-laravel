<?php

namespace App\Services;

use App\ValueObjects\ProphetForecast;
use Illuminate\Http\Client\Response;
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

        // Left unset, the default temperature is tuned for open-ended writing.
        // These answers summarise numbers the app already computed, so ask for
        // the least improvisation the model will give.
        $generationConfig = [
            'temperature' => (float) config('services.gemini.temperature'),
            'maxOutputTokens' => (int) config('services.gemini.max_output_tokens'),
        ];

        // Reasoning models spend tokens thinking before they write, and those
        // tokens come out of maxOutputTokens — the same budget as the answer.
        // So a cap sized for the answer alone gets eaten by the reasoning and
        // the reply arrives cut off, or empty with finishReason MAX_TOKENS.
        // Length here is controlled by the prompt ("maksimal 3 kalimat"), not
        // by a hard cut mid-sentence, so budget 0 (no thinking) is the right
        // default. Set GEMINI_THINKING_BUDGET to a negative number to omit the
        // field entirely — models older than the reasoning generation reject
        // it with a 400.
        $thinkingBudget = (int) config('services.gemini.thinking_budget');

        if ($thinkingBudget >= 0) {
            $generationConfig['thinkingConfig'] = ['thinkingBudget' => $thinkingBudget];
        }

        try {
            $response = $this->send($url, $apiKey, $prompt, $generationConfig);

            // thinkingConfig is the one field here whose acceptance depends on
            // which model GEMINI_MODEL resolves to: the reasoning generation
            // takes it, everything before rejects the whole request with a
            // 400. Rather than make that an operator's problem to notice and
            // diagnose, drop the field and try once more — a 400 means the
            // request was malformed, so there is nothing to back off from.
            if ($response->status() === 400 && isset($generationConfig['thinkingConfig'])) {
                // Worded as a hypothesis, not a diagnosis: a 400 can also mean
                // a bad key, and matching on the error text instead would miss
                // any rejection Google words differently. The retry costs one
                // call and the real reason is logged below either way.
                Log::warning('Gemini returned 400; retrying without thinkingConfig in case this model rejects it', [
                    'model' => $model,
                    'message' => $response->json('error.message') ?? 'Unknown error',
                ]);

                unset($generationConfig['thinkingConfig']);
                $response = $this->send($url, $apiKey, $prompt, $generationConfig);
            }
        } catch (Throwable $e) {
            // Detail to the log, not to whoever asked: the message can carry the
            // request URL and other internals.
            Log::error('Gemini request failed', ['error' => $e->getMessage()]);

            return '⚠️ Koneksi ke AI gagal. Coba lagi sebentar lagi.';
        }

        if ($response->failed()) {
            Log::warning('Gemini returned an error', [
                'status' => $response->status(),
                'model' => $model,
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

        $text = $this->joinParts($response->json('candidates.0.content.parts'));
        $finishReason = $response->json('candidates.0.finishReason');

        if ($text === null) {
            // An empty answer whose budget ran out is not "no response": the
            // model was still working when it hit the ceiling. Distinguish it,
            // because the fix is a bigger GEMINI_MAX_OUTPUT_TOKENS (or a
            // thinking budget of 0), not a retry.
            if ($finishReason === 'MAX_TOKENS') {
                Log::warning('Gemini used its whole output budget without producing text', [
                    'max_output_tokens' => $generationConfig['maxOutputTokens'],
                    'thinking_budget' => $generationConfig['thinkingConfig']['thinkingBudget'] ?? 'unset',
                    'usage' => $response->json('usageMetadata'),
                ]);

                return '⚠️ Jawaban AI habis di batas panjang sebelum sempat ditulis.';
            }

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
     * One generateContent call. Separate so the request can be reissued with
     * a different generationConfig without rebuilding everything around it.
     *
     * @param  array<string, mixed>  $generationConfig
     */
    private function send(string $url, string $apiKey, string $prompt, array $generationConfig): Response
    {
        return Http::timeout(config('services.gemini.timeout'))
            // The key goes in a header, not ?key= in the URL. Guzzle puts the
            // full effective URL into its ConnectionException message, so with
            // the key as a query parameter every timeout or reset wrote the
            // live API key into the log — and the caller handed that same
            // message back to the user, so it also appeared in Telegram
            // replies and the dashboard modal.
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => $generationConfig,
            ]);
    }

    /**
     * A candidate's answer is a *list* of parts, not one. Reading only
     * parts[0] — which is what this did — silently dropped everything after
     * the first, so a long answer arrived cut off at an arbitrary point with
     * finishReason STOP, i.e. looking complete. Parts flagged as thoughts are
     * the model's reasoning, not its answer, and are skipped.
     *
     * @param  array<int, array<string, mixed>>|null  $parts
     */
    private function joinParts(?array $parts): ?string
    {
        if (empty($parts)) {
            return null;
        }

        $texts = [];

        foreach ($parts as $part) {
            if (($part['thought'] ?? false) === true) {
                continue;
            }

            if (isset($part['text']) && $part['text'] !== '') {
                $texts[] = $part['text'];
            }
        }

        return empty($texts) ? null : implode('', $texts);
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
