<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceAlertRequest;
use App\Models\StockRef;
use App\Models\UserPriceAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a subscriber set/cancel their own watchlist price alerts —
 * checked and delivered over Telegram by idx:check-price-alerts. Every
 * query here is scoped to the authenticated user, never an ID taken from
 * the route/request, matching PortfolioService's pattern.
 */
class PriceAlertController extends Controller
{
    public function index(Request $request): View
    {
        return view('alerts.index', [
            'alerts' => $request->user()->priceAlerts()->with('stockRef')->latest()->get(),
            'tickerOptions' => StockRef::query()->orderBy('ticker')->get(['ticker', 'nama_perusahaan']),
        ]);
    }

    public function store(StorePriceAlertRequest $request): RedirectResponse
    {
        $request->user()->priceAlerts()->create($request->validated());

        return redirect()->route('alerts.index')->with('status', 'Alert berhasil dibuat.');
    }

    public function destroy(Request $request, UserPriceAlert $alert): RedirectResponse
    {
        abort_unless($alert->user_id === $request->user()->id, 403);

        $alert->delete();

        return redirect()->route('alerts.index')->with('status', 'Alert dihapus.');
    }
}
