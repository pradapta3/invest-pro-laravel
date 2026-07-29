<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BroadcastSignalRequest;
use App\Models\StockPrice;
use App\Services\StockScreenerService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;

/**
 * Manual "push to Telegram" actions from the dashboard, stock detail page
 * and Titan Radar, plus the dashboard's "Broadcast Top Picks" digest
 * button. Replaces the `action=send_telegram` POST handlers duplicated in
 * index.php and titan_scan.php, and telegram_bot.php's standalone
 * broadcast script.
 *
 * Unlike the legacy handlers, every figure in the outgoing message is
 * recomputed here from the ticker rather than trusted from the request
 * body — see BroadcastSignalRequest.
 */
class TelegramBroadcastController extends Controller
{
    public function __construct(
        private readonly TelegramBotService $bot,
        private readonly StockScreenerService $screener,
    ) {
    }

    public function signal(BroadcastSignalRequest $request): JsonResponse
    {
        $ticker = $request->normalizedTicker();
        $price = StockPrice::query()->with('stockRef')->find($ticker);

        if ($price === null || $price->stockRef === null) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan.'], 404);
        }

        return $this->broadcastResponse($this->bot->broadcast($this->bot->buildSignalMessage($price->stockRef, $price)));
    }

    public function titanSignal(BroadcastSignalRequest $request): JsonResponse
    {
        $ticker = $request->normalizedTicker();
        $signal = $this->screener->titan()->first(fn ($s) => $s->price->ticker === $ticker);

        if ($signal === null) {
            return response()->json(['status' => 'error', 'message' => 'Sinyal Titan tidak ditemukan untuk ticker ini.'], 404);
        }

        return $this->broadcastResponse($this->bot->broadcast($this->bot->buildTitanSignalMessage($signal)));
    }

    public function digest(): JsonResponse
    {
        return $this->broadcastResponse($this->bot->broadcast($this->bot->buildQuadraDigest()));
    }

    /**
     * TelegramBotService::broadcast() never throws — a missing bot token
     * or empty recipient list just yields an empty/all-false $sent map,
     * so every caller here used to report {"status":"ok"} regardless of
     * whether anything actually reached Telegram. Turn that into an
     * honest response instead.
     *
     * @param  array<string, bool>  $sent
     */
    private function broadcastResponse(array $sent): JsonResponse
    {
        if (empty($sent)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Belum ada chat ID Telegram yang dikonfigurasi. Isi TELEGRAM_BOT_TOKEN dan TELEGRAM_DEFAULT_CHAT_ID (atau TELEGRAM_BROADCAST_CHAT_IDS) di .env.',
                'sent' => $sent,
            ], 422);
        }

        if (! in_array(true, $sent, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim ke semua chat ID. Cek TELEGRAM_BOT_TOKEN dan storage/logs/telegram-bot.log.',
                'sent' => $sent,
            ], 502);
        }

        return response()->json(['status' => 'ok', 'sent' => $sent]);
    }
}
