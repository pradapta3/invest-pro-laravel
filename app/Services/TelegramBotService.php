<?php

namespace App\Services;

use App\Models\StockPrice;
use App\Models\StockRef;
use App\Models\User;
use App\Models\UserPortfolio;
use App\Models\UserPriceAlert;
use App\ValueObjects\ScoreBreakdown;
use App\ValueObjects\TitanSignal;
use App\ValueObjects\TradingPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * All Telegram bot behaviour, consolidated from three legacy entry points
 * that had drifted out of sync with each other:
 *
 *  - telegram_bot.php: a one-shot "Quadra Report" broadcast digest.
 *  - webhook.php: a single-ticker snapshot responder (no command router).
 *  - bot_loop.php: a full command router (/HELP, /BSJP, /TITAN, /INVEST,
 *    /PROPHET, ticker snapshot, free chat) driven by an infinite
 *    getUpdates polling loop with sleep(2) — fundamentally incompatible
 *    with a stateless web request lifecycle.
 *
 * This service is the single command router, invoked from
 * TelegramWebhookController (see VerifyTelegramWebhookSecret for how the
 * webhook is authenticated). The polling loop is retired outright: a
 * webhook does everything bot_loop.php did without a permanently-running
 * process.
 */
class TelegramBotService
{
    public function __construct(
        private readonly StockScreenerService $screener,
        private readonly TechnicalAnalysisService $ta,
        private readonly AiGenerativeService $ai,
    ) {
    }

    /**
     * Route one Telegram update payload to the matching command handler.
     */
    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if ($message === null || ! isset($message['text'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $rawText = trim((string) $message['text']);
        $textUpper = strtoupper($rawText);

        if ($textUpper === '/HELP' || $textUpper === '/START') {
            $this->sendMessage($chatId, $this->helpMenuText());

            return;
        }

        // Handles both the manual "/LINK CODE" command and Telegram's
        // deep-link form "/START CODE" (what t.me/<bot>?start=CODE actually
        // sends as the message text) — same one-time-code handler either way.
        if (str_starts_with($textUpper, '/LINK') || str_starts_with($textUpper, '/START ')) {
            $code = trim(str_replace(['/LINK', '/START'], '', $textUpper));
            $this->handleLinkCommand($chatId, $code);

            return;
        }

        if ($textUpper === '/BSJP') {
            $this->sendMessage($chatId, $this->bsjpReplyText());

            return;
        }

        if ($textUpper === '/TITAN') {
            $this->sendMessage($chatId, $this->titanReplyText());

            return;
        }

        if ($textUpper === '/INVEST') {
            $this->sendMessage($chatId, $this->investReplyText());

            return;
        }

        if (str_starts_with($textUpper, '/PROPHET')) {
            $this->handleProphetCommand($chatId, trim(str_replace('/PROPHET', '', $textUpper)));

            return;
        }

        $ticker = str_replace(['/PLAN', '/ANALYZE', ' '], '', $textUpper);
        if (preg_match('/^[A-Z]{4}$/', $ticker)) {
            $this->handleTickerSnapshot($chatId, $ticker);

            return;
        }

        if (strlen($rawText) > 1) {
            $this->sendMessage($chatId, "🧠 Gemini:\n\n".$this->ai->chat($rawText), 'Markdown');
        }
    }

    public function sendMessage(string $chatId, string $text, string $parseMode = 'HTML'): bool
    {
        $token = config('services.telegram.bot_token');
        if (empty($token)) {
            Log::channel('telegram_bot')->warning('Telegram bot token is not configured.');

            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        if ($parseMode !== '') {
            $payload['parse_mode'] = $parseMode;
        }

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

        if (! $response->successful() || ! $response->json('ok')) {
            $description = (string) $response->json('description');

            // Telegram rejects the entire message when parse_mode is set and
            // the text has an unbalanced entity — one stray '*' or '_' and the
            // user gets nothing at all. Since some of this text is written by
            // Gemini, that is not a hypothetical. Resend unformatted rather
            // than drop the message.
            if ($parseMode !== '' && stripos($description, 'parse entities') !== false) {
                Log::channel('telegram_bot')->info('Telegram rejected the formatting; resending as plain text', [
                    'chat_id' => $chatId,
                    'parse_mode' => $parseMode,
                ]);

                return $this->sendMessage($chatId, $text, '');
            }

            Log::channel('telegram_bot')->warning('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'response' => $response->json(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Send to one subscriber's own linked chat (see /LINK) instead of the
     * shared broadcast channel — used by the per-ticker "kirim sinyal"
     * buttons so each subscriber only sees their own requests, not
     * everyone else's.
     *
     * @return array<string, bool> chat_id => success (empty if not linked yet)
     */
    public function sendToUser(?string $chatId, string $text, string $parseMode = 'HTML'): array
    {
        if ($chatId === null || $chatId === '') {
            return [];
        }

        return [$chatId => $this->sendMessage($chatId, $text, $parseMode)];
    }

    /**
     * Broadcast to every configured chat (config('services.telegram.broadcast_chat_ids'),
     * falling back to the single default chat id).
     *
     * @return array<string, bool> chat_id => success
     */
    public function broadcast(string $text, string $parseMode = 'HTML'): array
    {
        $chatIds = config('services.telegram.broadcast_chat_ids');
        if (empty($chatIds)) {
            $chatIds = array_filter([config('services.telegram.default_chat_id')]);
        }

        $results = [];
        foreach ($chatIds as $chatId) {
            $results[$chatId] = $this->sendMessage($chatId, $text, $parseMode);
        }

        return $results;
    }

    /**
     * The "Quadra Report" digest: top N per strategy across BSJP, Titan,
     * Magic Formula ("Quant Alpha") and Top Picks ("Swing"), replacing
     * telegram_bot.php's broadcast composition. Reuses the exact same
     * StockScreenerService strategies as the dashboard/scanner pages
     * instead of the digest's own, differently-thresholded copies.
     */
    public function buildQuadraDigest(): string
    {
        $limit = config('screener.quadra_report.per_category_limit');
        $date = now()->format('d M Y');

        $lines = ["🤖 <b>IDX AI: QUADRA REPORT</b>", "📅 {$date}", ''];

        $bsjp = $this->screener->bsjp()->take($limit);
        if ($bsjp->isNotEmpty()) {
            $lines[] = '🌙 <b>BSJP (Fast)</b>';
            foreach ($bsjp as $price) {
                $plan = $this->ta->buildTradingPlan($price, 'bsjp');
                $t = $price->stockRef->cleanTicker();
                $lines[] = "🚀 <b>{$t}</b> (Spike: ".round($price->volumeSpikeRatio(), 1)."x)";
                $lines[] = '   Last: '.number_format((float) $price->close_price).' -> TP: '.number_format($plan->takeProfit);
                $lines[] = '';
            }
        }

        $titan = $this->screener->titan()->take($limit);
        if ($titan->isNotEmpty()) {
            $lines[] = '⚡ <b>TITAN SNIPER (High Vol)</b>';
            foreach ($titan as $signal) {
                $t = $signal->price->stockRef->cleanTicker();
                $lines[] = "🔥 <b>{$t}</b> (Vol: ".round($signal->volumeSpikeRatio, 1).'x Avg)';
                $lines[] = "   Entry: <code>{$signal->plan->entryText()}</code>";
                $lines[] = '';
            }
        }

        $quant = $this->screener->magicFormula()->take($limit);
        if ($quant->isNotEmpty()) {
            $lines[] = '🧠 <b>QUANT ALPHA (Value)</b>';
            foreach ($quant as $price) {
                $t = $price->stockRef->cleanTicker();
                $lines[] = "💎 <b>{$t}</b> (ROE: ".round((float) $price->stockRef->roe).'%)';
                $lines[] = '   PER: '.round((float) $price->stockRef->pe_ratio, 1).'x | Fair Value';
                $lines[] = '';
            }
        }

        $swing = $this->screener->topPicks()
            ->sortByDesc(fn (StockPrice $p) => $this->ta->calculateScore($p, $p->stockRef)->total())
            ->take($limit);
        if ($swing->isNotEmpty()) {
            $lines[] = '🏆 <b>TOP SWING (AI Score)</b>';
            foreach ($swing as $price) {
                $score = $this->ta->calculateScore($price, $price->stockRef);
                $plan = $this->ta->buildTradingPlan($price, 'swing');
                $t = $price->stockRef->cleanTicker();
                $lines[] = "✅ <b>{$t}</b> (Score: {$score->total()})";
                $lines[] = '   TP: <code>'.number_format($plan->takeProfit).'</code> | SL: '.number_format($plan->stopLoss);
                $lines[] = '';
            }
        }

        $lines[] = '💡 <i>Disclaimer On. DYOR.</i>';

        return implode("\n", $lines);
    }

    /**
     * Single-ticker AI snapshot, shared by the /webhook single-lookup
     * behaviour and the bot's bare-ticker command.
     */
    public function buildSnapshotMessage(StockRef $ref, StockPrice $price): string
    {
        $score = $this->ta->calculateScore($price, $ref);
        $plan = $this->ta->buildTradingPlan($price, 'swing');
        $flow = (float) $price->close_price > (float) $price->vwap ? 'Accumulation 🟢' : 'Distribution 🔴';
        $t = $ref->cleanTicker();

        $lines = [
            "🤖 <b>AI SNAPSHOT: {$t}</b>",
            $ref->nama_perusahaan,
            '--------------------------------',
            "📊 Score: <b>{$score->total()}/100</b>",
            '📢 Verdict: <b>'.$this->verdictWithEmoji($score).'</b>',
            '💰 Price: '.number_format((float) $price->close_price),
            "🌊 Flow: {$flow}",
            '',
            '🎯 <b>TRADING PLAN:</b>',
            "🛒 Entry: <code>{$plan->entryText()}</code>",
            '✅ Target: <code>'.number_format($plan->takeProfit).'</code>',
            '🛑 Stoploss: <code>'.number_format($plan->stopLoss).'</code>',
            "⚖️ RRR: 1 : {$plan->riskRewardRatio}",
            '',
            '💡 <i>Data Realtime. Disclaimer On.</i>',
        ];

        return implode("\n", $lines);
    }

    /**
     * Manual broadcast triggered from the dashboard/detail page's
     * "send signal" button. Unlike the legacy version, all figures are
     * recomputed here from the ticker rather than trusting client-POSTed
     * numbers.
     */
    public function buildSignalMessage(StockRef $ref, StockPrice $price): string
    {
        $score = $this->ta->calculateScore($price, $ref);
        $plan = $this->ta->buildTradingPlan($price, 'swing');
        $flow = (float) $price->close_price > (float) $price->vwap ? 'AKUM' : 'DIST';
        $t = $ref->cleanTicker();

        $lines = [
            "🤖 <b>AI SIGNAL ALERT: {$t}</b>",
            '--------------------------------',
            "📊 Score: <b>{$score->total()}/100</b> (".$this->verdictWithEmoji($score).')',
            '💰 Price: '.number_format((float) $price->close_price),
            "⚖️ R/R Ratio: <b>1:{$plan->riskRewardRatio}</b>",
            "🌊 Flow: {$flow}",
            '',
            '🎯 <b>TRADING PLAN:</b>',
            "🛒 Entry: <code>{$plan->entryText()}</code>",
            '✅ Target: <code>'.number_format($plan->takeProfit).'</code>',
            '🛑 Stoploss: <code>'.number_format($plan->stopLoss).'</code>',
            '--------------------------------',
            '🔍 <a href="'.route('stocks.show', $ref->ticker).'">Lihat Analisa Detail</a>',
        ];

        return implode("\n", $lines);
    }

    public function buildTitanSignalMessage(TitanSignal $signal): string
    {
        $t = $signal->price->stockRef->cleanTicker();
        $valFmt = number_format((float) $signal->price->value_transaction / 1_000_000_000, 1).'M';

        $lines = [
            "{$signal->tierIcon()} <b>TITAN RADAR: {$t}</b>",
            '--------------------------------',
            "🏆 Tier: <b>Class {$signal->tier}</b> (Power: {$signal->score})",
            '💰 Price: '.number_format((float) $signal->price->close_price),
            '📊 Vol Spike: <b>'.round($signal->volumeSpikeRatio, 1).'x</b>',
            "💸 Value: {$valFmt}",
            '',
            '🎯 <b>TITAN PLAN:</b>',
            '✅ TP1: '.number_format($signal->plan->takeProfit),
            '🚀 TP2: '.number_format((float) $signal->plan->takeProfit2),
            '🛑 STOP: '.number_format($signal->plan->stopLoss),
            '--------------------------------',
            '🔍 <a href="'.route('stocks.show', $signal->price->ticker).'">Check Chart</a>',
        ];

        return implode("\n", $lines);
    }

    /**
     * idx:check-price-alerts — a watchlist target price the user set has
     * been crossed.
     */
    public function buildPriceAlertMessage(UserPriceAlert $alert, StockPrice $price): string
    {
        $t = $alert->stockRef?->cleanTicker() ?? str_replace('.JK', '', $alert->ticker);
        $dirLabel = $alert->direction === 'above' ? 'naik ke atas' : 'turun ke bawah';

        $lines = [
            "🔔 <b>PRICE ALERT: {$t}</b>",
            '--------------------------------',
            'Harga sekarang: Rp '.number_format((float) $price->close_price),
            "Target Anda ({$dirLabel}): Rp ".number_format((float) $alert->target_price),
            '--------------------------------',
            '🔍 <a href="'.route('stocks.show', $alert->ticker).'">Lihat Detail</a>',
        ];

        return implode("\n", $lines);
    }

    /**
     * idx:check-price-alerts — an open position's price fell to/through
     * the stop-loss captured at purchase time (PortfolioService::buy()).
     */
    public function buildStopLossAlertMessage(UserPortfolio $position, StockPrice $price): string
    {
        $t = $position->stockRef?->cleanTicker() ?? str_replace('.JK', '', $position->ticker);

        $lines = [
            "🛑 <b>STOP LOSS TERSENTUH: {$t}</b>",
            '--------------------------------',
            'Harga sekarang: Rp '.number_format((float) $price->close_price),
            'Level Stop Loss: Rp '.number_format((float) $position->stop_loss),
            'Avg Beli Anda: Rp '.number_format((float) $position->avg_price),
            '--------------------------------',
            '⚠️ Pertimbangkan review posisi Anda.',
            '🔍 <a href="'.route('portfolio.index').'">Lihat Portofolio</a>',
        ];

        return implode("\n", $lines);
    }

    /**
     * idx:check-price-alerts — an open position's price reached the take-
     * profit captured at purchase time (PortfolioService::buy()).
     */
    public function buildTakeProfitAlertMessage(UserPortfolio $position, StockPrice $price): string
    {
        $t = $position->stockRef?->cleanTicker() ?? str_replace('.JK', '', $position->ticker);

        $lines = [
            "🎯 <b>TARGET PROFIT TERCAPAI: {$t}</b>",
            '--------------------------------',
            'Harga sekarang: Rp '.number_format((float) $price->close_price),
            'Target Profit: Rp '.number_format((float) $position->target_price),
            'Avg Beli Anda: Rp '.number_format((float) $position->avg_price),
            '--------------------------------',
            '✅ Pertimbangkan take profit sebagian/semua.',
            '🔍 <a href="'.route('portfolio.index').'">Lihat Portofolio</a>',
        ];

        return implode("\n", $lines);
    }

    /**
     * Links this Telegram chat to whichever app user currently holds the
     * (short-lived) code — generated from the app's "Pengaturan Telegram"
     * page. Deliberately does NOT accept a raw chat_id from the app side;
     * the chat_id can only ever come from Telegram itself sending us this
     * message, so there's no way to link an account to a chat you don't
     * control.
     */
    private function handleLinkCommand(string $chatId, string $code): void
    {
        if ($code === '') {
            $this->sendMessage($chatId, 'Format: <code>/LINK KODE</code> — ambil kode dari halaman Pengaturan Telegram di aplikasi.');

            return;
        }

        $user = User::query()
            ->where('telegram_link_code', $code)
            ->where('telegram_link_code_expires_at', '>', now())
            ->first();

        if ($user === null) {
            $this->sendMessage($chatId, '❌ Kode tidak valid atau sudah kedaluwarsa. Ambil kode baru dari halaman Pengaturan Telegram.');

            return;
        }

        $user->update([
            'telegram_chat_id' => $chatId,
            'telegram_link_code' => null,
            'telegram_link_code_expires_at' => null,
        ]);

        $this->sendMessage($chatId, "✅ Berhasil terhubung ke akun <b>{$user->email}</b>. Sinyal yang Anda kirim dari aplikasi sekarang akan masuk ke chat ini.");
    }

    private function handleProphetCommand(string $chatId, string $ticker): void
    {
        if (! preg_match('/^[A-Z]{4}$/', $ticker)) {
            $this->sendMessage($chatId, 'Format: /PROPHET BBCA');

            return;
        }

        $this->sendMessage($chatId, '🔮 <i>Menghitung regresi linear...</i>');

        $price = StockPrice::query()->find($ticker.'.JK');
        $forecast = $price ? $this->ta->prophetTrend($price->closeHistory()) : null;

        if ($forecast === null) {
            $this->sendMessage($chatId, '❌ Data historis JSON kosong/kurang.');

            return;
        }

        $ai = $this->ai->analyzeProphetForecast($ticker, $forecast);

        $lines = [
            "🔮 PROPHET: {$ticker}",
            '-----------------',
            "📈 Trend: {$forecast->status}",
            '🧮 Forecast: Rp '.number_format($forecast->forecast),
            '',
            "🧠 Analisa AI:\n{$ai}",
        ];

        $this->sendMessage($chatId, implode("\n", $lines), 'Markdown');
    }

    private function handleTickerSnapshot(string $chatId, string $ticker): void
    {
        $price = StockPrice::query()->with('stockRef')->find($ticker.'.JK');

        if ($price === null || $price->stockRef === null) {
            // HTML markup, so parse_mode must be HTML — sent as 'Markdown' the
            // <b> tags were shown to the user as literal text. The AI half is
            // escaped because it is the one part of this string the app didn't
            // write, and a '<' in it would break the parse.
            $ai = e($this->ai->analyzeUnknownTicker($ticker));
            $this->sendMessage($chatId, "❌ Data <b>{$ticker}</b> tidak ditemukan.\n\n🧠 <b>AI:</b> {$ai}", 'HTML');

            return;
        }

        $this->sendMessage($chatId, $this->buildSnapshotMessage($price->stockRef, $price));
    }

    private function bsjpReplyText(): string
    {
        $signals = $this->screener->bsjp()->take(10);
        if ($signals->isEmpty()) {
            return 'Tidak ada sinyal BSJP.';
        }

        $lines = ['🌙 <b>BSJP SCANNER</b>', ''];
        foreach ($signals as $price) {
            $t = $price->stockRef->cleanTicker();
            $spike = round($price->volumeSpikeRatio(), 1);
            $lines[] = "🚀 <b>{$t}</b> (Vol {$spike}x) - Close: ".number_format((float) $price->close_price);
        }

        return implode("\n", $lines);
    }

    private function titanReplyText(): string
    {
        $signals = $this->screener->titan()->take(10);
        if ($signals->isEmpty()) {
            return 'Pasar sepi.';
        }

        $lines = ['⚡ <b>TITAN SCANNER</b>', ''];
        foreach ($signals as $signal) {
            $t = $signal->price->stockRef->cleanTicker();
            $lines[] = "⚡ <b>{$t}</b> (Spike ".round($signal->volumeSpikeRatio, 1).'x) - Val: '
                .round((float) $signal->price->value_transaction / 1_000_000_000, 1).' M';
        }

        return implode("\n", $lines);
    }

    private function investReplyText(): string
    {
        $picks = $this->screener->valueInvesting();
        if ($picks->isEmpty()) {
            return 'Data kosong.';
        }

        $lines = ['💎 <b>VALUE INVESTING</b>', ''];
        foreach ($picks as $price) {
            $t = $price->stockRef->cleanTicker();
            $lines[] = "💎 <b>{$t}</b> (ROE ".round((float) $price->stockRef->roe).'%) - PER: '
                .round((float) $price->stockRef->pe_ratio, 1).'x';
        }

        return implode("\n", $lines);
    }

    private function verdictWithEmoji(ScoreBreakdown $score): string
    {
        return match ($score->verdict()) {
            'STRONG BUY' => 'STRONG BUY 🚀',
            'BUY' => 'BUY ✅',
            'AVOID' => 'AVOID ⛔',
            default => 'NEUTRAL ⚖️',
        };
    }

    private function helpMenuText(): string
    {
        return implode("\n", [
            '🤖 <b>IDX BOT PRO MENU</b>',
            '',
            '🔗 <b>/LINK [KODE]</b>',
            'Hubungkan chat ini ke akun Anda (ambil kode dari halaman Pengaturan Telegram di aplikasi).',
            '',
            '🔮 <b>/PROPHET [KODE]</b>',
            'Prediksi masa depan (Math + AI).',
            '',
            '🌙 <b>/BSJP</b>',
            'Scanner Saham Copet Sore.',
            '',
            '⚡ <b>/TITAN</b>',
            'Scanner Volume Spike.',
            '',
            '💎 <b>/INVEST</b>',
            'Scanner Fundamental Murah.',
            '',
            '🔍 <b>[KODE]</b> (cth: BBCA)',
            'Cek Snapshot Teknikal.',
            '',
            '🧠 <b>Chat Bebas</b>',
            'Tanya apa saja ke Gemini.',
        ]);
    }
}
