<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use App\Models\UserPortfolio;
use App\Models\UserPriceAlert;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;

/**
 * Notifies subscribers over their own linked Telegram chat (see
 * TelegramLinkController) when:
 *  - a watchlist price alert they set (UserPriceAlert) gets crossed, or
 *  - an open position's live price reaches the stop-loss/take-profit
 *    captured at purchase time (PortfolioService::buy()).
 *
 * Both are one-shot: a price alert is marked triggered_at and never
 * re-checked, and a position's sl_alerted_at/tp_alerted_at stop it firing
 * again until the position changes (a top-up resets both — see buy()).
 * Without this, nothing here would ever repeat-spam a user every 15
 * minutes just because price is still past the line.
 */
class CheckPriceAlerts extends Command
{
    protected $signature = 'idx:check-price-alerts';

    protected $description = 'Check watchlist price alerts and portfolio SL/TP, notify subscribers over their linked Telegram chat';

    public function __construct(private readonly TelegramBotService $bot)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $watchlistSent = $this->checkWatchlistAlerts();
        $portfolioSent = $this->checkPortfolioAlerts();

        $this->info("Sent {$watchlistSent} watchlist alert(s), {$portfolioSent} portfolio SL/TP alert(s).");

        return self::SUCCESS;
    }

    private function checkWatchlistAlerts(): int
    {
        $sent = 0;

        $alerts = UserPriceAlert::query()
            ->whereNull('triggered_at')
            ->with(['user', 'stockRef'])
            ->get();

        foreach ($alerts as $alert) {
            if (! $alert->user->hasLinkedTelegram()) {
                continue;
            }

            $price = StockPrice::query()->find($alert->ticker);
            if ($price === null || ! $alert->isCrossedBy((float) $price->close_price)) {
                continue;
            }

            $this->bot->sendToUser($alert->user->telegram_chat_id, $this->bot->buildPriceAlertMessage($alert, $price));
            $alert->update(['triggered_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    private function checkPortfolioAlerts(): int
    {
        $sent = 0;

        $positions = UserPortfolio::query()
            ->where(function ($q) {
                $q->whereNull('sl_alerted_at')->orWhereNull('tp_alerted_at');
            })
            ->with(['user', 'stockRef.price'])
            ->get();

        foreach ($positions as $position) {
            if (! $position->user->hasLinkedTelegram()) {
                continue;
            }

            $price = $position->stockRef?->price;
            if ($price === null) {
                continue;
            }

            $current = (float) $price->close_price;

            if ($position->stop_loss !== null && $position->sl_alerted_at === null && $current <= (float) $position->stop_loss) {
                $this->bot->sendToUser($position->user->telegram_chat_id, $this->bot->buildStopLossAlertMessage($position, $price));
                $position->update(['sl_alerted_at' => now()]);
                $sent++;
            }

            if ($position->target_price !== null && $position->tp_alerted_at === null && $current >= (float) $position->target_price) {
                $this->bot->sendToUser($position->user->telegram_chat_id, $this->bot->buildTakeProfitAlertMessage($position, $price));
                $position->update(['tp_alerted_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }
}
