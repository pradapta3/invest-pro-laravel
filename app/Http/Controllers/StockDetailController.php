<?php

namespace App\Http\Controllers;

use App\Models\StockRef;
use App\Services\MarketData\MarketDataService;
use App\Services\TechnicalAnalysisService;
use Illuminate\View\View;

/**
 * Full single-stock analysis page (detail.php): TradingView chart, AI
 * score breakdown, pivot levels, seasonality, sector peer comparison,
 * MA20 backtest and news sentiment.
 */
class StockDetailController extends Controller
{
    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly TechnicalAnalysisService $ta,
    ) {
    }

    public function show(string $ticker): View
    {
        $normalized = StockRef::normalizeTicker($ticker);

        $ref = StockRef::query()->with('price')->find($normalized);
        abort_if(! $ref || ! $ref->price, 404, "Data {$ticker} tidak ditemukan.");

        $price = $ref->price;

        $peers = StockRef::query()
            ->with('price')
            ->where('sector', $ref->sector)
            ->where('ticker', '!=', $ref->ticker)
            ->orderByDesc('market_cap')
            ->limit(5)
            ->get()
            ->filter(fn (StockRef $r) => $r->price !== null)
            ->values();

        $score = $this->ta->calculateScore($price, $ref);
        $pivots = $this->ta->pivotPoints((float) $price->high_price, (float) $price->low_price, (float) $price->close_price);

        $seasonality = $this->marketData->monthlySeasonality($ref->ticker);
        $seasonalityStats = $this->marketData->seasonalityStats($seasonality);

        $dailyChart = $this->marketData->dailyChart($ref->ticker, '1y', '1d');
        $backtest = $this->ta->backtestMa20Strategy($dailyChart['close']);

        return view('stocks.detail', [
            'ref' => $ref,
            'price' => $price,
            'peers' => $peers,
            'score' => $score,
            'pivots' => $pivots,
            'monthlyByYear' => $seasonality,
            'seasonality' => $seasonalityStats,
            'backtest' => $backtest,
            'ta' => $this->ta,
        ]);
    }
}
