<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Single inbound entry point for the Telegram bot, replacing webhook.php
 * (single-ticker snapshot only) and bot_loop.php (full command router via
 * an infinite getUpdates polling loop). The route is CSRF-exempt (see
 * bootstrap/app.php) and instead authenticated per-request by
 * VerifyTelegramWebhookSecret.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(private readonly TelegramBotService $bot)
    {
    }

    public function __invoke(Request $request): Response
    {
        $this->bot->handleUpdate($request->all());

        return response('', 200);
    }
}
