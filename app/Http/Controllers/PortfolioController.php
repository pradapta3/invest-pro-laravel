<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InsufficientLotsException;
use App\Http\Requests\DepositRequest;
use App\Http\Requests\StoreTradeRequest;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Simulated portfolio dashboard: holdings, wallet, transaction history,
 * and the buy/sell/deposit actions (portfolio.php). Every call into
 * PortfolioService is scoped to the *authenticated* user — see that
 * class's docblock for why the user id always comes from the session,
 * never from request input.
 */
class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolio)
    {
    }

    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $summary = $this->portfolio->summary($userId);

        return view('portfolio.index', [
            'wallet' => $summary['wallet'],
            'holdings' => $summary['holdings'],
            'totalEquity' => $summary['total_equity'],
            'unrealizedPl' => $summary['unrealized_pl'],
            'winRate' => $summary['win_rate'],
            'history' => $this->portfolio->recentHistory($userId, 10),
            'tickerOptions' => $this->portfolio->tickerOptions(),
        ]);
    }

    public function trade(StoreTradeRequest $request): RedirectResponse
    {
        $ticker = $request->normalizedTicker();
        $price = (float) $request->validated('price');
        $lot = (int) $request->validated('lot');
        $userId = $request->user()->id;

        try {
            if ($request->validated('action') === 'buy') {
                $this->portfolio->buy($userId, $ticker, $price, $lot);
            } else {
                $this->portfolio->sell($userId, $ticker, $price, $lot);
            }
        } catch (InsufficientFundsException|InsufficientLotsException $e) {
            return redirect()->route('portfolio.index')->withErrors(['lot' => $e->getMessage()]);
        }

        return redirect()->route('portfolio.index')->with('status', 'Order berhasil dieksekusi.');
    }

    public function deposit(DepositRequest $request): RedirectResponse
    {
        $this->portfolio->deposit($request->user()->id, (float) $request->validated('amount'));

        return redirect()->route('portfolio.index')->with('status', 'Deposit berhasil.');
    }
}
