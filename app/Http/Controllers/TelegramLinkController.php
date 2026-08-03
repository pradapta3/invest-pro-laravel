<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Lets each subscriber link their own Telegram chat so the per-ticker
 * "kirim sinyal" buttons reach their own phone instead of the site's
 * shared broadcast channel (config('services.telegram.broadcast_chat_ids')
 * — still used for the admin-only "Broadcast Top Picks" digest).
 *
 * The link itself only ever happens from the Telegram side (TelegramBotService::
 * handleLinkCommand) — this controller only ever hands out a short-lived
 * one-time code, it never accepts a chat_id directly from the browser.
 */
class TelegramLinkController extends Controller
{
    public function show(Request $request): View
    {
        return view('telegram.link', ['user' => $request->user()]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $request->user()->update([
            'telegram_link_code' => strtoupper(Str::random(8)),
            'telegram_link_code_expires_at' => now()->addMinutes(15),
        ]);

        return redirect()->route('telegram.link');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->update([
            'telegram_chat_id' => null,
            'telegram_link_code' => null,
            'telegram_link_code_expires_at' => null,
        ]);

        return redirect()->route('telegram.link');
    }
}
